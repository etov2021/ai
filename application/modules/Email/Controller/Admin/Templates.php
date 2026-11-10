<?php

namespace Email\Controller\Admin;

use Email\Model\Templates as ModelTemplates;
use Micro\Application\Controller\Admin;
use Micro\Model\Entity\EntityInterface;
use Micro\Form\Form;
use Email\Model\Mailer;
use Micro\Http\Response;

class Templates extends Admin
{
    protected $model = ModelTemplates::class;

    public function indexAction()
    {
        if (($response = parent::indexAction()) instanceof Response) {
            return $response;
        }

        $form = new Form(module_path('Email') . '/Resources/forms/admin/templates-filters.php');
        $form->populate($this->view->filters);

        return $this->view->assign([
            'form' => $form
        ]);
    }

    public function preValidate(Form $form, EntityInterface $entity, array $post)
    {
        $post = $this->request->getPost();

        if (isset($post['btnSend'])) {
            if ($post['testEmails'] == '') {
                $form->getElement('testEmails')->addError('Добавете тестови мейли');
                $form->markAsError();
            } else {
                $to = explode(PHP_EOL, $post['testEmails']);
                if (is_array($to)) {
                    $mailer = new Mailer();
                    $mailer->send($post['alias'], $post['testEmails'], []);
                }
            }
        }
    }

    /**
     * @param Form $form
     * @param EntityInterface $entity
     */
    protected function prepareForm(Form $form, EntityInterface $entity)
    {
        if ($entity->getId()) {
            if (!$entity->getDescriptionIsEditor()) {
                $form->getElement('description')->setClass('form-control');
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
        if (!$data['descriptionIsEditor']) {
            $form->getElement('description')->setClass('form-control');
        } else {
            $form->getElement('description')->setClass('tinymce form-control');
        }
    }
}