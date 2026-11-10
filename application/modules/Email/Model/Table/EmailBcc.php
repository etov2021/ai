<?php

namespace Email\Model\Table;

use Micro\Database\Table\TableAbstract;

class EmailBcc extends TableAbstract
{
    protected $_name = 'EmailBcc';

    protected static $_emails;

    public function fetch()
    {
        static::$_emails = $this->getAdapter()->fetchPairs("SELECT id, email FROM $this->_name");

        return static::$_emails;
    }

    public function isExist($email)
    {
        if (static::$_emails === null) {
            static::$_emails = $this->getAdapter()->fetchPairs("SELECT id, email FROM $this->_name");
        }

        if (in_array($email, static::$_emails)) {
            return true;
        }

        return false;
    }
}