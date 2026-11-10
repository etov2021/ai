<?php

namespace Domains\Controller\Admin;

use Micro\Application\Controller\Admin;
use Domains\Model\Domains as ModelDomains;
use Micro\Http\Response\RedirectResponse;
use Micro\Form\Form;
use Micro\Model\Entity\EntityInterface;

class Index extends Admin
{
    protected $model = ModelDomains::class;

    protected function prepareForm(Form $form, EntityInterface $entity)
    {
        if ($entity->getId()) {
            $form->languages->setValue($entity->getLanguages() ? explode(',', $entity->getLanguages()) : []);
        }
    }

    public function changeDomainAction()
    {
        $id  = $this->request->getParam('id');
        $ref = $this->request->getParam('ref');

        $domains = (new ModelDomains())->fetchCachedPairs();

        if (isset($domains[$id])) {
            setcookie('admin_domain', $domains[$id], null, '/', null, null, true);
        }

        return new RedirectResponse($ref ?: route_reset('admin-home', ['lang' => null]));
    }

    public function deleteAction()
    {
        throw new \Exception('Not implemented');
    }
}