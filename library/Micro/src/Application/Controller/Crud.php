<?php

namespace Micro\Application\Controller;

use Micro\Exception\Exception as CoreException;
use Micro\Application\Controller;
use Micro\View\View;
use Micro\Http\Response\RedirectResponse;
use Micro\Form\Form;
use Micro\Grid;
use Micro\Application\Utils;
use Micro\Model\Entity\EntityInterface;
use Micro\Http\Response;
use Micro\Http\Request;
use Micro\Container\ContainerInterface;
use Micro\Container\SharedContainer;
use Micro\Translator\Language\LanguageInterface;
use Micro\Model\ModelInterface;
use Micro\Model\TranslationInterface as ModelTranslationInterface;
use Micro\Model\Entity\TranslationInterface;
use Micro\Model\TranslationDatabaseAbstract;
use Micro\Grid\Renderer\RendererInterface;

class Crud extends Controller
{
    use FiltersTrait;

    protected $model;

    /**
     * @var ModelInterface
     */
    protected $modelInstance;

    /**
     * @var int
     */
    protected $ipp = 25;

    /**
     * @var string
     */
    protected $sortField;

    /**
     * @var string
     */
    protected $sortDir = 'DESC';

    /**
     * @var View
     */
    protected $view;

    /**
     * @var string
     */
    public $scope;

    /**
     * @var RendererInterface
     */
    protected $gridRenderer;

    /**
     * @var string
     */
    const CURRENT_LANG_KEY = 'current_lang';

    /**
     * @param Request $request
     * @param Response $response
     * @param ContainerInterface $container
     */
    public function __construct(Request $request, Response $response, ContainerInterface $container)
    {
        parent::__construct($request, $response, $container);

        $this->view = new View();
    }

    /**
     * @throws \Exception
     * @return ModelInterface
     */
    public function getModel()
    {
        if ($this->modelInstance !== \null) {
            return $this->modelInstance;
        }

        $modelCandidate = \null;

        if (\is_string($this->model) && \class_exists($this->model, \true)) {
            $modelCandidate = $this->model;
        }

        if ($modelCandidate !== \null) {
            if ($this->container->has($modelCandidate)) {
                $this->modelInstance = $this->container->get($modelCandidate);
            } else {
                $this->modelInstance = new $modelCandidate;
            }
        }

        if ($this->modelInstance === \null) {
            throw new CoreException(\sprintf(
                '$model property in [%s] is not valid.',
                \get_class($this)
            ));
        } else if (!$this->modelInstance instanceof ModelInterface) {
            throw new CoreException(\sprintf(
                'Model [%s] must be instanceof %s',
                (\is_object($this->modelInstance) ? \get_class($this->modelInstance) : (\is_string($this->modelInstance) ? $this->modelInstance : \gettype($this->modelInstance))),
                ModelInterface::class
            ));
        }

        if ($this->modelInstance instanceof ModelTranslationInterface) {
            $this->modelInstance->setLanguage($this->getCurrentLanguage());
        }

        return $this->modelInstance;
    }

    /**
     * @return View
     */
    public function getView()
    {
        return $this->view;
    }

    /**
     * @return string
     */
    protected function getGridPath()
    {
        return \null;
    }

    /**
     * @return string
     */
    protected function getGridTemplate()
    {
        return \null;
    }

    /**
     * @return string
     */
    protected function getFormPath()
    {
        return \null;
    }

    /**
     * @return string
     */
    protected function getFormTemplate()
    {
        return \null;
    }

    /**
     * @return LanguageInterface
     */
    public function getDefaultLanguage()
    {
        return SharedContainer::getInstance()->get('default_language');
    }

    /**
     * @return LanguageInterface
     */
    public function getUiLanguage()
    {
        return SharedContainer::getInstance()->get('language');
    }

    /**
     * @return LanguageInterface
     */
    public function getCurrentLanguage()
    {
        return SharedContainer::getInstance()->get('current_language');
    }

    /**
     * @return RedirectResponse|View
     */
    public function indexAction()
    {
        $module = $this->request->getParam('module');
        $controller = $this->request->getParam('controller');

        if ($this->request->isPost()) {
            if ($this->request->getPost('btnAdd')) {
                return new RedirectResponse(\route(\null, ['action' => 'add', 'id' => \null, 'page' => \null, static::CURRENT_LANG_KEY => \null]));
            }
        }

        if (($filters = $this->handleFilters()) instanceof Response) {
            return $filters;
        }

        $model = $this->getModel();

        $model->addFilters($filters);

        // hook
        $this->modifyModel($filters);

        $page = \max(1, $this->request->getParam('page', 1));
        $sort = ($this->sortField ?: $model->getIdentifier()) . '_' . $this->sortDir;
        $sort = $this->request->getParam('sort', $sort);

        $sortParts = \explode('_', $sort);

        $orderField = \array_shift($sortParts);
        $orderField = $orderField ?: $model->getIdentifier();

        $orderDir = \array_shift($sortParts);
        $orderDir = $orderDir ? \strtoupper($orderDir) : $this->sortDir;

        if (!in_array(\strtoupper($orderDir), ['ASC', 'DESC'])) {
            $orderDir = 'DESC';
        }

        $model->addOrder($orderField, $orderDir);

        $gridPath = \module_path(\ucfirst(Utils::camelize($module)), '/Resources/grids' . ($this->scope ? '/' . $this->scope : ''));

        $_gridPath = $this->getGridPath();
        if ($_gridPath !== \null) {
            $gridPath = $_gridPath;
        } else {
            $gridPath = $gridPath . '/' . $controller . '.php';
        }

        $this->container->get('event')->trigger('Crud.Model.Prepare', ['model' => $model]);

        $grid = new Grid\Grid(
            $model,
            $gridPath
        );

        if ($this->gridRenderer) {
            $grid->setRenderer($this->gridRenderer);
        }

        $this->container->get('event')->trigger('Crud.Grid.Prepare', ['grid' => $grid, 'view' => $this->view]);

        if (($column = $grid->getColumn($orderField)) instanceof Grid\Column) {
            $column->setSorted($orderDir);
        }

        $defaultIpp = ($grid->getIpp() ? $grid->getIpp() : $this->ipp);

        $ipp = $this->request->getParam('ipp', $defaultIpp);

        $grid->setIpp($ipp);
        $grid->setPageNumber($page);

        $_gridTemplate = $this->getGridTemplate();
        if ($_gridTemplate !== \null) {
            $gridTemplate = $_gridTemplate;
        } else {
            $gridTemplate = ($this->scope ? $this->scope . '/' : '') . $controller . '/index';
        }

        $this->view->setTemplate($gridTemplate);

        return $this->view->assign([
            'grid'    => $grid,
            'filters' => $filters
        ]);
    }

    /**
     * @param array $filters
     */
    protected function modifyModel(array $filters)
    {
    }

    /**
     * @param EntityInterface $entity
     * @return RedirectResponse|View
     */
    public function addAction(?EntityInterface $entity = \null)
    {
        $module = $this->request->getParam('module');
        $controller = $this->request->getParam('controller');

        $model = $this->getModel();

        if ($entity === \null) {
            $entity = $model->createEntity();
        }

        $formPath = \module_path(\ucfirst(Utils::camelize($module)), '/Resources/forms' . ($this->scope ? '/' . $this->scope : ''));

        $_formPath = $this->getFormPath();
        if ($_formPath !== \null) {
            $formPath = $_formPath;
        } else {
            $formPath = $formPath . '/' . $controller . '-add.php';
        }

        $form = new Form(
            $formPath
        );

        $form->populate($entity->toArray());

        // hook
        $this->prepareForm($form, $entity);

        // event prepare form
        $this->container->get('event')->trigger('Crud.Form.Prepare', compact('form', 'entity', 'model'));

        if ($this->request->isPost()) {

            try {

                if ($this->request->contentLengthExceed($length, $max)) {
                    throw new CoreException(sprintf('Съдържанието (%s байта) което се опитвате да изпратите е по-голямо от post_max_size (%s байта)', $length, $max), 500);
                }

                $post = $this->request->getPost();

                if (isset($post['btnBack'])) {
                    $backTo = $this->request->getParam('backTo');
                    if ($backTo) {
                        return new RedirectResponse($backTo);
                    } else {
                        return new RedirectResponse(\route(\null, ['action' => 'index', 'id' => \null, static::CURRENT_LANG_KEY => \null]));
                    }
                }

                if (isset($post['btnDeleteTranslation']) && $model instanceof TranslationDatabaseAbstract) {
                    if ($entity instanceof TranslationInterface
                        && $entity[$model->getIdentifier()]
                        && $entity->getLanguageId() != $this->getDefaultLanguage()->getId()
                        && $entity->hasTranslation($this->getCurrentLanguage())
                    ) {
                        $model->deleteTranslation($entity, $this->getCurrentLanguage());
                        return (new RedirectResponse(\route(\null, ['action' => 'index', 'id' => \null, static::CURRENT_LANG_KEY => \null])))->withFlash('Преводът беше изтрит');
                    }
                }

                $post = Utils::arrayMapRecursive('trim', $post);

                // hook
                $this->modifyPost($form, $entity, $post);

                // hook
                $this->preValidate($form, $entity, $post);

                $form->isValid($post);

                // hook
                $this->postValidate($form, $entity, $post);

                // event validate form
                $this->container->get('event')->trigger('Crud.Form.Validate', compact('form', 'entity', 'post'));

                if (!$form->hasErrors()) {
                    
                    if (method_exists($entity, 'getOwner')) {
                        $entity->getOwner();
                    }
     
                    $fn = function ($v) {
                        return \is_scalar($v) ? \trim($v) : $v;
                    };

                    $data = Utils::arrayMapRecursive(
                        $fn,
                        $form->getValues() + $post,
                        \true
                    );

                    // hook
                    $this->modifyData($data);

                    $entity->setFromArray($data);

                    // hook
                    $this->modifyEntity($entity, $data);

                    $model->save($entity);

                    if (isset($post['btnApply'])) {
                        $redirectResponse = new RedirectResponse(\route(\null, ['action' => 'edit', 'id' => $entity[$model->getIdentifier()]]));
                    } else {
                        $backTo = $this->request->getParam('backTo');
                        if ($backTo) {
                            $redirectResponse = new RedirectResponse($backTo);
                        } else {
                            $redirectResponse = new RedirectResponse(\route(\null, ['action' => 'index', 'id' => \null, static::CURRENT_LANG_KEY => \null]));
                        }
                    }

                    return $redirectResponse->withFlash('Информацията е записана');
                }

            } catch (\Throwable $e) {

                if ($entity[$model->getIdentifier()]) {
                    $redirectResponse = new RedirectResponse(\route(\null, ['action' => 'edit', 'id' => $entity[$model->getIdentifier()]]));
                } else {
                    $redirectResponse = new RedirectResponse(\route(\null, ['action' => 'add', 'id' => \null, static::CURRENT_LANG_KEY => \null]));
                }

                return $redirectResponse->withFlash((\is_debug()? $e->getMessage() : 'Възникна грешка. Опитайте по-късно'), 'danger');
            }
        }

        $_formTemplate = $this->getFormTemplate();

        if ($_formTemplate !== \null) {
            $formTemplate = $_formTemplate;
        } else {
            $formTemplate = ($this->scope ? $this->scope . '/' : '') . $controller . '/add';
        }

        $this->view->setTemplate($formTemplate);

        return $this->view->assign([
            'form' => $form,
            'item' => $entity
        ]);
    }

    /**
     * @param Form $form
     * @param EntityInterface $entity
     */
    protected function prepareForm(Form $form, EntityInterface $entity)
    {
    }

    /**
     * @param Form $form
     * @param EntityInterface $entity
     * @param array $data
     */
    protected function modifyPost(Form $form, EntityInterface $item, array &$data)
    {
    }

    /**
     * @param Form $form
     * @param EntityInterface $entity
     * @param array $data
     */
    protected function preValidate(Form $form, EntityInterface $item, array $data)
    {
    }

    /**
     * @param Form $form
     * @param EntityInterface $entity
     * @param array $data
     */
    protected function postValidate(Form $form, EntityInterface $item, array $data)
    {
    }

    /**
     * @param array $data
     */
    protected function modifyData(array &$data)
    {
    }

    /**
     * @param EntityInterface $entity
     * @param array $data
     */
    protected function modifyEntity(EntityInterface $entity, array $data)
    {
    }

    /**
     * @throws \Exception
     * @return \Micro\Http\Response\RedirectResponse|\Micro\View\View
     */
    public function editAction()
    {
        $model = $this->getModel();

        $entity = $model->find((int) $this->request->getParam('id', 0));

        if ($entity === \null) {
            throw new CoreException(\sprintf('Записът не е намерен'), 404);
        }

        return $this->addAction($entity);
    }

    /**
     * @throws \Exception
     * @return \Micro\Http\Response\RedirectResponse|\Micro\View\View
     */
    public function previewAction()
    {
        $model = $this->getModel();

        $entity = $model->find((int) $this->request->getParam('id', 0));

        if ($entity === null) {
            throw new CoreException(sprintf('Записът не е намерен'), 404);
        }

        $module = $this->request->getParam('module');
        $controller = $this->request->getParam('controller');

        $formPath = module_path(ucfirst(Utils::camelize($module)), '/Resources/forms' . ($this->scope ? '/' . $this->scope : ''));
        $formPath = $formPath . '/' . $controller . '-add.php';

        $form = new Form(
            $formPath
        );

        $form->populate($entity->toArray());

        // hook
        $this->prepareForm($form, $entity);

        // event prepare form
        $this->container->get('event')->trigger('Crud.Form.Prepare', compact('form', 'entity', 'model'));

        if ($this->request->isPost()) {
            $post = $this->request->getPost();
            if (isset($post['btnEdit'])) {
                return new RedirectResponse(route(null, ['action' => 'edit']));
            }
            if (isset($post['btnBack'])) {
                $backTo = $this->request->getParam('backTo');
                if ($backTo) {
                    return new RedirectResponse($backTo);
                } else {
                    return new RedirectResponse(route(null, ['action' => 'index', 'id' => null, static::CURRENT_LANG_KEY => null]));
                }
            }
        }

        $formTemplate = 'shared::' . ($this->scope ? $this->scope . '/' : '') . 'preview';

        $this->view->setTemplate($formTemplate);

        return $this->view->assign([
            'form' => $form,
            'item' => $entity
        ]);
    }

    /**
     * @return \Micro\Http\Response\RedirectResponse
     */
    public function deleteAction()
    {
        $id = (int) $this->request->getParam('id', 0);
        $ids = (array) $this->request->getParam('ids', []);

        if ($id) {
            $ids = [$id];
        }

        $ids = \array_filter($ids);

        $affected = 0;

        if (!empty($ids)) {
            $model = $this->getModel();
            $model->addWhere('id', $ids);
            $items = $model->getItems();
            foreach ($items as $item) {
                try {
                    $affected += $model->delete($item);
                } catch (\Throwable $e) {
                    if (\is_debug()) {
                        \flash()->setMessage($e->getMessage(), 'warning');
                    }
                }
            }
        }

        $redirectResponse = new RedirectResponse(\route(\null, ['action' => 'index', 'id' => \null, 'ids' => \null, static::CURRENT_LANG_KEY => \null]));

        return $redirectResponse->withFlash(\sprintf('Информацията е записана. Бяха изтрити %d запис/а', $affected));
    }

    /**
     * Activate item Action
     * @param number $active
     */
    public function activateAction($active = 1)
    {
        $id = (int) $this->request->getParam('id', 0);
        $ids = (array) $this->request->getParam('ids', []);

        if ($id) {
            $ids = [$id];
        }

        $ids = \array_filter($ids);

        $affected = 0;

        if (!empty($ids)) {
            $model = $this->getModel();
            $model->addWhere('id', $ids);
            $items = $model->getItems();
            foreach ($items as $item) {
                try {
                    $affected += $model->activate($item, $active);
                } catch (\Throwable $e) {

                }
            }
        }

        $redirectResponse = new RedirectResponse(\route(\null, ['action' => 'index', 'id' => \null, 'ids' => \null, static::CURRENT_LANG_KEY => \null]));

        return $redirectResponse->withFlash(\sprintf('Информацията е записана. Бяха %s %d запис/а', ($active ? 'активирани' : 'деактивирани'), $affected));
    }

    /**
     * Deactivate item Action
     */
    public function deactivateAction()
    {
        return $this->activateAction(0);
    }

    public function langHelperAction()
    {
        $model = $this->getModel();

        if ($model instanceof ModelTranslationInterface) {
            $model->setLanguage($this->getDefaultLanguage());
        }

        $item = $model->find((int) $this->request->getParam('id'));

        $field = $this->request->getParam('field');

        if ($item) {
            if ($field) {
                if (isset($item[$field])) {
                    return (string) $item[$field];
                }
            } else {
                return $item->toJson();
            }
        }

        return '';
    }

    public function copyRowsAction()
    {
        $domainId = 1;

        $ids = (array) $this->request->getParam('ids', []);

        $ids = \array_filter($ids);

        if (!empty($ids)) {
            $model = $this->getModel();
            $model->addWhere('id', $ids);
            $items = $model->getItems();
            $domains = (new \Domains\Model\Domains())->getItems();
            if (isset($domains[$domainId])) {
                $domain = $domains[$domainId];
                foreach ($items as $item) {
                    if ($item instanceof \Domains\Model\Entity\DomainInterface) {
                        $settings = $item->getDomainSettings();
                        if (isset($settings['domain_depended']) && !$settings['domain_depended']) {
                            continue;
                        }
                        try {
                            $newItem = clone $item;
                            $newItem->setId(null);
                            $newItem->setLanguageId($domain->getDefaultLanguage());
                            $newItem->setTranslations(pow(2, $domain->getDefaultLanguage() - 1));
                            $model->save($newItem);
                            $model->getTable()->update(['domainId' => $domainId], ['id = ?' => $item[$model->getIdentifier()]]);
                        } catch (\Exception $e) {

                        }
                    }
                }
            }
        }

        $redirectResponse = new RedirectResponse(\route(\null, ['action' => 'index', 'id' => \null, 'ids' => \null, static::CURRENT_LANG_KEY => \null]));

        return $redirectResponse->withFlash();
    }
    
    public function copyAction()
    {
        $id = $this->request->getParam('id', 0);
        $params = $this->request->getParams();

        $redirectResponse = new RedirectResponse(\route(\null, ['action' => 'index', 'id' => \null, 'ids' => \null, static::CURRENT_LANG_KEY => \null]));
        
        if ($id) {
            $model = $this->getModel();
            
            if(method_exists($model, 'copy')) {
                $model->copy($id, $params);
            } else {
                return $redirectResponse->withFlash('Copy error: no current method', 'warning');
            }
            
        } else {
            return $redirectResponse->withFlash('Copy error: please select item', 'warning');
        }
        
        return $redirectResponse->withFlash();
    }
    
    public function isOwner($item = null)
    {
        if ($item && is_object($item) && method_exists($item, 'getOwner')) {
      
            $user = identity();

            return $item->getOwner() == $user->getId();
        }

        return true;
    }
}