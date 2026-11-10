<?php

namespace UserManagement\Controller\Admin;

use Micro\Form\Form;
use Micro\Model\Entity\EntityInterface;
use Micro\Cache as MicroCache;
use UserManagement\Helper\ResourceTreeTrait;
use UserManagement\Model\Groups as ModelGroups;
use Micro\Application\Controller\Admin;
use UserManagement\Helper\GroupsTree;

class Groups extends Admin
{
    use ResourceTreeTrait;

    protected $model = ModelGroups::class;

    protected function prepareForm(Form $form, EntityInterface $entity)
    {
        if ($entity->getId() && $entity->getCustomRights()) {
            $form->getElement('customRights')->setValue(\json_decode($entity->getCustomRights(), \true));
        }

        if ($form->getElement('parentId')) {
            $groupsTreeHelper = new GroupsTree();
            $groupsTreeHelper->setOnSelect(function ($select) {
                $select->where('alias <> ?', 'guest');
            });
            $form->getElement('parentId')->setMultiOptions(
                $groupsTreeHelper->flat(
                    $groupsTreeHelper->getTree(), '---', [(int) $entity->getId()]
                )
            );
        }

        $this->view->assign([
            'tree' => $this->getResourceTree($entity)
        ]);
    }

    protected function modifyEntity(EntityInterface $entity, array $data)
    {
        $this->bindEntityToRights($entity);

        $cache = cache('json');

        if ($cache instanceof MicroCache\Core) {
            $cache->remove('Acl');
        }
    }

    public function activateAction($active = 1)
    {
        throw new \Exception('Not implemented');
    }

    public function deactivateAction()
    {
        throw new \Exception('Not implemented');
    }

    public function deleteAction()
    {
        throw new \Exception('Not implemented');
    }
}