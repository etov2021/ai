<?php

namespace Categories\Controller\Admin;

use Categories\Model\Categories as ModelCategories;
use Micro\Model\ModelInterface;
use Micro\Model\Entity\EntityInterface;
use Micro\Form\Form;
use Micro\Http\Response\RedirectResponse;
use Categories\Helper\Tree;
use Categories\Model\Table;
use Micro\Exception\Exception as CoreException;
use Categories\Model\Entity\CategoryInterface;
use Micro\Application\Controller\Admin;
use Micro\Http\Response;

class Categories extends Admin
{
    protected $model = ModelCategories::class;

    protected $target;

    protected $hasPermissions = false;

    /**
     * {@inheritDoc}
     * @see \Micro\Application\Controller::init()
     */
    public function init()
    {
        $this->view->addPath(\module_path('Categories', '/Resources/views'));
    }

    /**
     * @return ModelInterface
     */
    public function getModel()
    {
        if ($this->modelInstance !== \null) {
            return $this->modelInstance;
        }

        $model = parent::getModel();

        if ($this->target === null || !class_exists($this->target, true)) {
            throw new CoreException(__METHOD__ . ' :: Target entity class must be set');
        }

        $test = new $this->target;

        if (!$test instanceof CategoryInterface) {
            if ($this->target === null || !class_exists($this->target, true)) {
                throw new CoreException(__METHOD__ . ' :: Target entity class must be instance of ' . CategoryInterface::class);
            }
        }

        $model->setSettings($test->getCategorySettings());

        return $model;
    }

    /**
     * {@inheritDoc}
     * @see \Micro\Application\Controller\Crud::getGridPath()
     */
    protected function getGridPath()
    {
        return __DIR__ . '/../../Resources/grids/admin/categories.php';
    }

    /**
     * {@inheritDoc}
     * @see \Micro\Application\Controller\Crud::getGridTemplate()
     */
    protected function getGridTemplate()
    {
        return 'admin/categories/index';
    }

    /**
     * {@inheritDoc}
     * @see \Micro\Application\Controller\Crud::getFormPath()
     */
    protected function getFormPath()
    {
        return __DIR__ . '/../../Resources/forms/admin/categories-add.php';
    }

    /**
     * {@inheritDoc}
     * @see \Micro\Application\Controller\Crud::getFormTemplate()
     */
    protected function getFormTemplate()
    {
        return 'admin/categories/add';
    }

    /**
     * {@inheritDoc}
     * @see \Micro\Application\Controller\Crud::indexAction()
     */
    public function indexAction()
    {
        $settings = $this->getModel()->getSettings();
        $this->view->assign('settings', $settings);


        if (!$settings['parent']) {

            $response = parent::indexAction();

            if ($settings['sortable'] && !$response instanceof Response) {
                $this->view->grid->setIpp(null);
                $this->view->grid->setIppRanger(false);
                $this->getModel()->resetOrder()->addOrder('order', 'asc');
                foreach ($this->view->grid->getColumns() as $column) {
                    $column->setSortable(0);
                    if ($column->getName() === 'id') {
                        $column->setViewScript('admin::_grid-sort');
                        $column->setTitle('');
                        $column->setClass('text-center');
                    }
                }
            }

            return $response;
        }

        if ($this->request->isPost()) {
            if ($this->request->getPost('btnAdd')) {
                return new RedirectResponse(route(\null, array('action' => 'add')));
            }
        }

        $tree = new Tree($settings['target']);
        $this->view->assign('items', $tree->getTree());
    }

    /**
     * @param Form $form
     * @param EntityInterface $entity
     */
    protected function prepareForm(Form $form, EntityInterface $entity)
    {
        $settings = $this->getModel()->getSettings();

        if ($settings['parent']) {
            $treeHelper = new \Categories\Helper\Tree($settings['target']);
            $form->parentId->setMultiOptions($treeHelper->flat($treeHelper->getTree(), '---', [(int) $entity->getId()]));
        } else {
            $form->removeElement('parentId');
        }

        $this->view->assign('hasPermissions', $this->hasPermissions);
    }

    protected function modifyPost(Form $form, EntityInterface $item, array &$data)
    {
        if (isset($data['permalink']) && empty($data['permalink']) && isset($data['name']) && !empty($data['name'])) {
            $data['permalink'] = \str_slug($data['name']);
        }
    }

    public function ajaxSetOrderAction()
    {
        $data = $this->request->getPost('data', '[]');
        $data = \json_decode($data, true);
        $data = \is_array($data) ? $data : array();

        $model = new Table\Categories();
        $model->updateTree($data);

        return '';
    }

    public function ajaxReorderAction()
    {
        $settings = $this->getModel()->getSettings();

        if (!$settings['parent'] && $settings['sortable']) {
            $sort = $this->request->getPost('sort', []);
            if (is_array($sort)) {
                $table = $this->getModel()->getTable();
                $table->getAdapter()->beginTransaction();
                foreach ($sort as $k => $id) {
                    $table->update(
                        ['order' => ($k + 1)],
                        ['categoryTarget = ?' => $settings['target'], 'id = ?' => $id]
                    );
                }
                $this->getModel()->removeCache();
                $table->getAdapter()->commit();
            }
        }

        return '';
    }
}