<?php

namespace UserManagement\Model\Table;

use Micro\Database\Table\TableAbstract;

class UsersGroupsRel extends TableAbstract
{
    protected $_name = 'UsersGroupsRel';

    public function getUserGroups($userId)
    {
        $select = $this->select(true)
                       ->setIntegrityCheck(false)
                       ->joinInner('UsersGroups', 'UsersGroupsRel.groupId = UsersGroups.id', ['alias'])
                       ->where('userId = ?', (int) $userId)
                       ->reset('columns')
                       ->columns(['UsersGroups.id', 'UsersGroups.alias']);

        return $this->getAdapter()->fetchPairs($select);
    }
}