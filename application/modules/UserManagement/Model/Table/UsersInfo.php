<?php

namespace UserManagement\Model\Table;

use Micro\Database\Table\TableAbstract;

class UsersInfo extends TableAbstract
{
    protected $_name = 'UsersInfo';

    protected $_referenceMap = array(
        'User' => array(
            'columns'       => 'userId',
            'refTableClass' => Users::class,
            'refColumns'    => 'id'
        )
    );
}