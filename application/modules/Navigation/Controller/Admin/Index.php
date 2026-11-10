<?php

namespace Navigation\Controller\Admin;

use Micro\Form\Form;
use Navigation\Model;
use Navigation\Helper;
use Micro\Http\Response\RedirectResponse;
use Micro\Application\Utils;
use Micro\Http\Response\JsonResponse;
use Micro\Router\Route;
use Micro\Application\Controller\Admin;
use Micro\Http\Response;
use Navigation\Model\Items;
use Micro\Database\Expr;
use Navigation\Model\Menu;
use Domains\Model\Domains;

class Index extends Admin
{
    protected $model = Model\Menu::class;

    /**
     * @var Model\Items
     */
    protected $itemsModel;

    protected function getItemsModel()
    {
        if ($this->itemsModel === null) {
            $this->itemsModel = new Model\Items();
            $this->itemsModel->setLanguage($this->getCurrentLanguage());
        }

        return $this->itemsModel;
    }

    public function itemsAction()
    {
        $menuId = $this->request->getParam('menuId');

        $model = $this->getModel();
        $model->addWhere('id', (int) $menuId);

        $menu = $model->getItem();

        if ($menu === \null) {
            return new RedirectResponse(route(\null, array('action' => 'index', 'menuId' => \null)));
        }

        if ($this->request->isPost()) {
            if ($this->request->getPost('btnAdd')) {
                return new RedirectResponse(route(\null, array('action' => 'add-item')));
            } else if ($this->request->getPost('btnBack')) {
                return new RedirectResponse(route(\null, array('action' => 'index', 'menuId' => \null)));
            }
        }

        $tree = new Helper\Tree($menu->getAlias());
        $items = $tree->getTree();

        $this->view->assign('menu', $menu);
        $this->view->assign('items', $items);

    }

    public function addItemAction()
    {
        $menuId = $this->request->getParam('menuId');
        $id = $this->request->getParam('id');

        $model = $this->getModel();
        $model->addWhere('id', (int) $menuId);

        $menu = $model->getItem();

        if ($menu === null) {
            return new RedirectResponse(route(\null, array('action' => 'index', 'menuId' => \null)));
        }

        $model = $this->getItemsModel();

        if ($id) {

            $item = $model->find((int) $id);

            if ($item === \null) {
                return new RedirectResponse(route(\null, array('action' => 'items', 'id' => \null, static::CURRENT_LANG_KEY => \null)));
            }

            $model->resetSelect(\true);

        } else {
            $item = $model->createEntity();
        }

        if ($item instanceof Model\Entity\Item) {

        }

        $form = new Form(module_path('Navigation', '/Resources/forms/admin/index-add-item.php'));

        $tree = new Helper\Tree($menu->getAlias());

        $form->parentId->setMultiOptions($tree->flat($tree->getTree(), '---', [(int) $id]));

        $form->populate($item->toArray());

        if ($item->getHandlerId()) {
            $handlers = explode(',', $item->getHandlerId());
            $form->handlerId->setValue($handlers);
        } else {
            $form->handlerId->setValue([]);
        }

        // event prepare form
        $this->container->get('event')->trigger('Crud.Form.Prepare', ['form' => $form, 'entity' => $item, 'model' => $model]);

        if ($this->request->isPost()) {

            $post = $this->request->getPost();

            if (isset($post['btnBack'])) {
                return new RedirectResponse(route(\null, array('action' => 'items', 'id' => \null, static::CURRENT_LANG_KEY => \null)));
            }

            if (isset($post['btnDeleteTranslation'])) {
                if ($item->getId()
                    && $item->getLanguageId() != $this->getDefaultLanguage()->getId()
                    && $item->hasTranslation($this->getCurrentLanguage())
                ) {
                    $model->deleteTranslation($item, $this->getCurrentLanguage());
                    return (new RedirectResponse(\route(\null, ['action' => 'items', 'id' => \null, static::CURRENT_LANG_KEY => \null])))->withFlash('Преводът беше изтрит');
                }
            }

            $form->isValid($post);

            if (!$form->hasErrors()) {

                if (isset($post['routeData'])) {
                    $routeData = $post['routeData'];
                } else {
                    $routeData = [];
                }

                foreach ($routeData as $k => $v) {
                    if (empty($v)) {
                        unset($routeData[$k]);
                    }
                }

                $fn = function ($v) {
                    return \is_scalar($v) ? \trim($v) : $v;
                };

                $data = Utils::arrayMapRecursive(
                    $fn,
                    $form->getValues() + $post,
                    \true
                );

                $item->setFromArray($data);

                $item->setMenuId($menuId);

                if ($item->getRoute() === \null) {
                    if ($item->getUrl() === \null) {
                        $item->setUrl('#');
                    }
                } else {
                    $item->setUrl(\null);
                }

                $item->setResource(\null);

                if ($item->getRoute()) {

                    if (($navigationHelper = $this->getNavigationHelper($item->getRoute())) !== \null) {
                        if (\method_exists($navigationHelper, 'decode')) {
                            $navigationHelper->decode($routeData, $item);
                        }
                    }

                    $route = $this->container->get('router')->getRoute($item->getRoute());

                    if ($route instanceof Route) {

                        $route = clone $route;

                        $resourceRouteData = $routeData;

                        if ($item->getQsaData()) {
                            $qsaData = [];
                            \parse_str($item->getQsaData(), $qsaData);
                            $resourceRouteData = \array_merge($resourceRouteData, $qsaData);
                        }

                        $route->setParams($resourceRouteData + $route->getDefaults());

                        $resource = $route->getHandler();

                        if ($resource instanceof \Closure) {
                            $resource = \call_user_func($resource->bindTo($route), $this->request, $this->response);
                        }

                        $item->setResource($resource);
                    }
                }

                $item->setRouteData(empty($routeData) ? \null : json_encode($routeData));

                if ($item->getOrder() === \null) {
                    if ($item->getParentId()) {
                        $and = ' AND parentId = ' . (int) $item->getParentId();
                    } else {
                        $and = ' AND parentId IS NULL';
                    }
                    $item->setOrder($model->getTable()->getAdapter()->fetchOne('SELECT IFNULL(MAX(`order`), 0) + 1 FROM MenuItems WHERE menuId = ' . (int) $menuId . $and));
                }

                try {

                    $model->save($item);

                    if (isset($post['btnApply'])) {
                        $redirectResponse = new RedirectResponse(route(\null, ['action' => 'add-item', 'id' => $item->getId()]));
                    } else {
                        $redirectResponse = new RedirectResponse(route(\null, ['action' => 'items', 'id' => \null, static::CURRENT_LANG_KEY => \null]));
                    }

                    return $redirectResponse->withFlash('Информацията е записана');

                } catch (\Exception $e) {

                    if ($item->getId()) {
                        $redirectResponse = new RedirectResponse(route(\null, ['action' => 'add-item', 'id' => $item->getId()]));
                    } else {
                        $redirectResponse = new RedirectResponse(route(\null, ['action' => 'items', 'id' => \null, static::CURRENT_LANG_KEY => \null]));
                    }

                    return $redirectResponse->withFlash((env('development') ? $e->getMessage() : 'Възникна грешка. Опитайте по-късно'), 'danger');
                }
            }
        }

        $this->view->assign('menu', $menu);
        $this->view->assign('item', $item);
        $this->view->assign('form', $form);
    }

    public function ajaxGetRouteDataAction()
    {
        $routeName = $this->request->getPost('route');
        $routeData = $this->request->getPost('routeData');
        $qsaData   = $this->request->getPost('qsaData');

        $routeData = $routeData ? json_decode($routeData, \true) : [];
        $routeData = is_array($routeData) ? $routeData : [];

        $route = $this->container->get('router')->getRoute($routeName);

        if ($route !== \null && \method_exists($route, 'compile')) {
            $route->compile();
        }

        $navigationData = [];

        $navigationHelperClass = null;

        if (($navigationHelper = $this->getNavigationHelper($routeName, $navigationHelperClass)) !== \null) {

            if (method_exists($navigationHelper, 'resolve')) {

                $menuModel = $this->getModel();
                $menuModel->addWhere('id', (int) $this->request->getParam('menuId'));

                $navigationData = $navigationHelper->resolve($routeData, $route, $menuModel->getItem());
            }
        }

        $this->view->assign('route', $route);
        $this->view->assign('routeData', $routeData);
        $this->view->assign('qsaData', $qsaData);
        $this->view->assign('navigationData', $navigationData);
        $this->view->assign('navigationHelper', $navigationHelper);
        $this->view->assign('navigationHelperClass', $navigationHelperClass);
    }

    protected function getNavigationHelper($routeName, &$navigationHelperClass = null)
    {
        $route = $this->container->get('router')->getRoute($routeName);

        if (!$route instanceof Route) {
            return \null;
        }

        if (($matches = $this->container->get('resolver')->canResolve($route->getHandler())) !== \null) {
            $parts = explode('\\', $matches[0]);
            $navigationHelperClass = \ucfirst(Utils::camelize($parts[0])) . '\\Navigation\\Route\\' . \ucfirst(Utils::camelize($routeName));
            if (\class_exists($navigationHelperClass, \true)) {
                $navigationHelper = new $navigationHelperClass($route);
                return $navigationHelper;
            }
        }

        return \null;
    }

    public function deleteItemAction()
    {
        $id = $this->request->getParam('id');

        $model = $this->getItemsModel();

        $entity = $model->find((int) $id);

        if ($entity) {
            $model->delete($entity);
        }

        return new RedirectResponse(route(\null, array('action' => 'items', 'id' => \null)));
    }

    public function ajaxSetOrderAction()
    {
        $data = $this->request->getPost('data', '[]');
        $data = \json_decode($data, true);
        $data = \is_array($data) ? $data : array();

        try {
            $menuItemsModel = new Model\Table\Items();
            $menuItemsModel->updateTree($data);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => 0, 'error' => $e->getMessage()]);
        }

        return new JsonResponse(['success' => 1]);
    }

    public function removeCacheAction()
    {
        $id = (int) $this->request->getPost('id');

        if ($id) {
            $this->getModel()->removeCacheByMenu($id);
        }

        return '';
    }

    public function copyAction()
    {
        $itemId = (int) $this->request->getParam('id');
        $menuId = (int) $this->request->getParam('menuId');
        $toMenuId = (int) $this->request->getParam('toMenuId');

        if (empty($toMenu = (new Menu())->getTable()->fetchRow(['id = ?' => $toMenuId]))) {
            goto escape;
        }

        $domains = (new Domains())->getItems();

        if (!isset($domains[$toMenu['domainId']])) {
            goto escape;
        }

        $domain = $domains[$toMenu['domainId']];

        if (empty($domain['defaultLanguage'])) {
            goto escape;
        }

        $model = new Items();
        $model->addWhere('menuId', $menuId);

        if ($itemId) {
            $model->addWhere('id', $itemId);
        } else {
            $model->addWhere(new Expr('parentId IS NULL'));
        }

        foreach ($model->getItems() as $item) {
            $model->clone($item, $toMenuId, $domain['defaultLanguage']);
        }

        $model->getTable()->getAdapter()->query('UPDATE MenuItems a SET a.translations = (SELECT bit_or(pow(2, b.languageId - 1)) FROM MenuItemsLang b WHERE a.id = b.itemId GROUP BY b.itemId)');

        escape:
        return new JsonResponse();
    }
}