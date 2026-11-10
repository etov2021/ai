<?php

namespace UserManagement\Model;

use Micro\Model\DatabaseAbstract;

class Groups extends DatabaseAbstract
{
    protected $entity = Entity\Group::class;

    protected $table = Table\Groups::class;
    
    const STATUS_GROUP_ALIAS = 'operator';
    const TRADERS_GROUP_ALIAS = 'traders';
    const PROCESSES_GROUP_ALIAS = 'processes';
    const MANAGERS_GROUP_ALIAS = 'managers';
    const OWNERS_GROUP_ALIAS = 'owners';

    public function getGroupIdByAlias($alias)
    {
        $callback = function () {
            return $this->fetchPairs(null, ['alias', 'id']);
        };

        $groups = cache_data('json', 'GroupsAliases', $callback, ['UserManagement_Model_Groups']);

        return isset($groups[$alias]) ? $groups[$alias] : 0;
    }
}