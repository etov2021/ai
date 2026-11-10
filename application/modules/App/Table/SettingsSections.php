<?php

namespace App\Table;

use Micro\Database\Table\TableAbstract;

class SettingsSections extends TableAbstract
{
    protected $_name = 'SettingsSections';

    public function fetchSections()
    {
        return $this->getAdapter()->fetchPairs('SELECT id, name FROM SettingsSections ORDER BY sort ASC');
    }
}