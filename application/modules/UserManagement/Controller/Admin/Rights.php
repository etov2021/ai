<?php

namespace UserManagement\Controller\Admin;

use Micro\Http\Response;
use Micro\Form\Form;
use UserManagement\Model\Rights as ModelRights;
use Micro\Application\Controller\Admin;

class Rights extends Admin
{
    protected $model = ModelRights::class;

    public function indexAction()
    {
        if (($response = parent::indexAction()) instanceof Response) {
            return $response;
        }

        $form = new Form(module_path('UserManagement') . '/Resources/forms/admin/rights-filters.php');

        $form->populate($this->view->filters);

        return $this->view->assign([
            'form' => $form
        ]);
    }
}