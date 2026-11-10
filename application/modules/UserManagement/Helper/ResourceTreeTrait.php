<?php

namespace UserManagement\Helper;

use Micro\Helper\Files as HelperFiles;
use Micro\Model\Entity\EntityInterface;

trait ResourceTreeTrait
{
    protected function bindEntityToRights(EntityInterface $entity)
    {
        $resources = \json_decode($this->request->getPost('rights', ''), \true);
        $resources = $resources ? \array_flip($resources) : [];

        $rights = [];

        foreach ($resources as $resource => $junk) {
            $resource = \str_replace('_', "\\", $resource);
            $resource = \explode("\\", $resource);
            $action = $resource[\count($resource) - 1];
            unset($resource[\count($resource) - 1]);
            $rights[\implode("\\", $resource) . "@" . $action] = \true;
        }

        $entity->setRights(\json_encode($rights));

        $customRights = $entity->getCustomRights();

        if (empty($customRights)) {
            $customRights = [];
        }

        $entity->setCustomRights(\json_encode($customRights));
    }

    protected function getResourceTree(EntityInterface $entity)
    {
        try {

            $resources = [];

            if ($entity->getId()) {
                $access = $entity->getRights();
                if ($access) {
                    $access = \json_decode($access, \true);
                    $access = $access ? $access : [];
                    foreach ($access as $resource => $junk) {
                        $resources[str_replace(["\\", "@"], "_", $resource)] = $junk;
                    }
                }
            }

            if ($this->request->isPost()) {
                $resources = \json_decode($this->request->getPost('rights', ''), \true);
                $resources = $resources ? \array_flip($resources) : [];
            }

            $tree = $this->buildResourceTree(
                HelperFiles::fetchResources(),
                $resources
            );

        } catch (\Exception $e) {

            $tree = [];
        }

        return $tree;
    }

    protected function buildResourceTree(array $items, array $accessRights)
    {
        $tree = [];

        foreach ($items as $item) {

            $sibling = [
                'id'   => $item['id'],
                'text' => translate('G_' . $item['name']),
            ];

            if (isset($accessRights[$item['id']])) {
                $sibling['state']['selected'] = \true;
            }

            if (!empty($item['resources'])) {
                $sibling['children'] = $this->buildResourceTree($item['resources'], $accessRights);
            } else {
                $sibling['icon'] = 'jstree-file';
            }

            if ($item['name'] == 'Controller') {
                if (!empty($sibling['children'])) {
                    $tree = $tree + $sibling['children'];
                }
            } else {
                $tree[] = $sibling;
            }
        }

        return $tree;
    }
}