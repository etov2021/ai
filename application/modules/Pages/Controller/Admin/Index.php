<?php

namespace Pages\Controller\Admin;

use Pages\Model\Pages as PagesModel;
use Micro\Form\Form;
use Micro\Http\Response;
use Micro\View\View;
use Micro\Application\Controller\Admin;
use Micro\Database\Expr;
use Micro\Model\Entity\EntityInterface;

class Index extends Admin
{
    protected $model = PagesModel::class;

    public function indexAction()
    {
        if (($response = parent::indexAction()) instanceof Response) {
            return $response;
        }

        $form = new Form(module_path('Pages') . '/Resources/forms/admin/index-filters.php');
        $form->populate($this->view->filters);

        return $this->view->assign([
            'form' => $form
        ]);
    }

    /**
     * @param Form $form
     * @param EntityInterface $entity
     */
    protected function prepareForm(Form $form, EntityInterface $entity)
    {
        if ($entity->getId()) {
            if (!$entity->getContentIsEditor()) {
                $form->getElement('content')->setClass('form-control');
            }
            if (!$entity->getContentMobileIsEditor()) {
                $form->getElement('contentMobile')->setClass('form-control');
            }
        }
    }

    /**
     * @param Form $form
     * @param EntityInterface $entity
     * @param array $data
     */
    protected function modifyPost(Form $form, EntityInterface $item, array &$data)
    {
        if (!$data['contentIsEditor']) {
            $form->getElement('content')->setClass('form-control');
        } else {
            $form->getElement('content')->setClass('tinymce form-control');
        }

        if (!$data['contentMobileIsEditor']) {
            $form->getElement('contentMobile')->setClass('form-control');
        } else {
            $form->getElement('contentMobile')->setClass('tinymce form-control');
        }
    }

    /**
     * {@inheritDoc}
     * @see \Micro\Application\Controller\Crud::modifyModel()
     */
    protected function modifyModel(array $filters)
    {
        $model = $this->getModel();

        $model->resetWhere();

        if (isset($filters['name']) && !empty($filters['name'])) {
            $model->addWhere(new Expr(sprintf('PagesLang.name LIKE "%1$s" OR Pages.alias LIKE "%1$s"', '%' . $filters['name'] . '%')));
        }

        if (isset($filters['categoryId'])) {
            $model->addWhere('categoryId', $filters['categoryId']);
        }

        if (isset($filters['type'])) {
            $model->addWhere('type', $filters['type']);
        }
    }

    public function viewAction()
    {
        $model = $this->getModel();

        $entity = $model->find((int) $this->request->getParam('id', 0));

        if ($entity === \null) {
            raise(sprintf('Записът не е намерен'), 404);
        }

        return ['item' => $entity];
    }
}