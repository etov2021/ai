<?php

namespace Log\Model;

use Micro\Database\Table\TableAbstract;
use Micro\Database\Table\Select;

class Log extends TableAbstract
{
    protected $_name = 'Log';

    public function applyFilters(Select $select, array $filters)
    {
        if (isset($filters['creator']) && $filters['creator']) {
            $creator = $this->getAdapter()->quote($filters['creator']);
            $userId = $this->getAdapter()->fetchOne('SELECT id FROM Users WHERE ' . sprintf('Users.username LIKE %s OR Users.email LIKE %s', $creator, $creator));
            $select->where('creator = ?', $userId);
        }

        if (isset($filters['module']) && $filters['module']) {
            $select->where('module LIKE ?', '%' . $filters['module'] . '%');
        }

        if (isset($filters['controller']) && $filters['controller']) {
            $select->where('controller LIKE ?', '%' . $filters['controller'] . '%');
        }

        if (isset($filters['action']) && $filters['action']) {
            $select->where('action LIKE ?', '%' . $filters['action'] . '%');
        }

        if (isset($filters['message']) && $filters['message']) {
            $select->where('message LIKE ?', '%' . $filters['message'] . '%');
        }

        if (isset($filters['startDate']) && $filters['startDate']) {
            $date = new \DateTime($filters['startDate']);
            $date->setTime(0, 0, 0);
            $select->where('time >= ?', $date->format('U'));
        }

        if (isset($filters['endDate']) && $filters['endDate']) {
            $date = new \DateTime($filters['endDate']);
            $date->setTime(23, 59, 59);
            $select->where('time <= ?', $date->format('U'));
        }
    }
}