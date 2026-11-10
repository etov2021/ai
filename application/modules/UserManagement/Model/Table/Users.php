<?php

namespace UserManagement\Model\Table;

use Micro\Database\Table\TableAbstract;

class Users extends TableAbstract
{
    protected $_name = 'Users';

    protected $_dependentTables = array(UsersInfo::class);
}