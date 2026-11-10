<?php

namespace Email\Model\Table;

use Micro\Database\Table\TableAbstract;

class Tracking extends TableAbstract
{
    protected $_name = 'EmailSendTracking';

    public function track($key, $data = null)
    {
        try {
            $row = $this->createRow();
            $row->hash = md5($key);
            $row->key = $key;
            $row->sended = expr('NOW()');
            $row->data = $data;
            $row->save();
        } catch (\Throwable $e) {

        }
    }

    public function isTracked($key)
    {
        $row = $this->fetchRow($this->createHybridSelect()->where('hash = ?', md5($key)));

        return $row ? true : false;
    }
}