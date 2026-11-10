<?php

namespace Nomenclatures\Controller\Admin;

use Nomenclatures\Model\Clients as Model;
use Micro\Application\Controller\Admin;
use Micro\Http\Response;
use Micro\Form\Form;

class Clients extends Admin
{
    protected $model = Model::class;

    public function indexAction()
    {
        if (($response = parent::indexAction()) instanceof Response) {
            return $response;
        }

        $form = new Form(module_path('Nomenclatures') . '/Resources/forms/admin/clients-filters.php');
        $form->populate($this->view->filters);

        return $this->view->assign([
            'form' => $form
        ]);
    }
}