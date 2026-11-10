<?php

namespace Nomenclatures\Model;

use Micro\Model\TranslationDatabaseAbstract;
use Micro\Model\Entity\EntityInterface;

class StatusInner extends TranslationDatabaseAbstract
{
    protected $entity = Entity\StatusInner::class;

    protected $table = Table\StatusInner::class;
    
    public function save(EntityInterface $entity)
    {
        if ($entity->getOrder() === \null) {
            $sql = '
                SELECT
                IFNULL(MAX(`order`), 0) + 1
                FROM NomStatusInner';
            $entity->setOrder($this->getTable()->getAdapter()->fetchOne($sql));
        }
        
        return parent::save($entity);
    }
}