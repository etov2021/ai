<?php

namespace Micro\Paginator\Adapter;

class DatabaseTableSelect extends DatabaseSelect
{
    /**
     * Database query
     *
     * @var \Micro\Database\Table\Select
     */
    protected $_select = \null;

    public function getItems($offset = \null, $itemCountPerPage = \null)
    {
        $this->_select->limit($itemCountPerPage, $offset);

        return $this->_select->getTable()->fetchAll($this->_select);
    }
}