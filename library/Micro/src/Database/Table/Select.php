<?php

namespace Micro\Database\Table;

use Micro\Database\Select as DatabaseSelect;
use Micro\Database\Expr;

class Select extends DatabaseSelect
{
    /**
     * Table schema for parent \Micro\Database\Table\TableAbstract
     *
     * @var array
     */
    protected $_info;

    /**
     * Table integrity override.
     *
     * @var array
     */
    protected $_integrityCheck = true;

    /**
     * Table instance that created this select object
     *
     * @var TableAbstract
     */
    protected $_table;

    /**
     * Class constructor
     *
     * @param TableAbstract $table
     * @internal param TableAbstract $adapter
     */
    public function __construct(TableAbstract $table)
    {
        parent::__construct($table->getAdapter());

        $this->setTable($table);
    }

    /**
     * Return the table that created this select object
     *
     * @return TableAbstract
     */
    public function getTable()
    {
        return $this->_table;
    }

    /**
     * Sets the primary table name and retrieves the table schema.
     *
     * @param TableAbstract $table
     * @return DatabaseSelect This \Micro\Database\Select object.
     * @throws Exception
     * @internal param TableAbstract $adapter
     */
    public function setTable(TableAbstract $table)
    {
        $this->_adapter = $table->getAdapter();
        $this->_info    = $table->info();
        $this->_table   = $table;

        return $this;
    }

    /**
     * Sets the integrity check flag.
     *
     * Setting this flag to false skips the checks for table joins, allowing
     * 'hybrid' table rows to be created.
     *
     * @param boolean
     * @return DatabaseSelect This \Micro\Database\Select object.
     */
    public function setIntegrityCheck($flag = true)
    {
        $this->_integrityCheck = $flag;
        return $this;
    }

    /**
     * Tests query to determine if expressions or aliases columns exist.
     *
     * @return boolean
     */
    public function isReadOnly()
    {
        $readOnly = false;
        $fields   = $this->getPart(Select::COLUMNS);
        $cols     = $this->_info[TableAbstract::COLS];

        if (!count($fields)) {
            return $readOnly;
        }

        foreach ($fields as $columnEntry) {
            $column = $columnEntry[1];
            $alias = $columnEntry[2];

            if ($alias !== null) {
                $column = $alias;
            }

            switch (true) {
                case ($column == self::SQL_WILDCARD):
                    break;

                case ($column instanceof Expr):
                case (!in_array($column, $cols)):
                    $readOnly = true;
                    break 2;
            }
        }

        return $readOnly;
    }

    /**
     * Adds a FROM table and optional columns to the query.
     *
     * The table name can be expressed
     *
     * @param  array|string|\Micro\Database\Expr|TableAbstract $name The table name or an
                                                                      * associative array relating
                                                                      * table name to correlation
                                                                      * name.
     * @param  array|string|\Micro\Database\Expr $cols The columns to select from this table.
     * @param  string $schema The schema name to specify, if any.
     * @return \Micro\Database\Table\Select This \Micro\Database\Table\Select object.
     */
    public function from($name, $cols = self::SQL_WILDCARD, $schema = null)
    {
        if ($name instanceof TableAbstract) {
            $info = $name->info();
            $name = $info[TableAbstract::NAME];
            if (isset($info[TableAbstract::SCHEMA])) {
                $schema = $info[TableAbstract::SCHEMA];
            }
        }

        return $this->joinInner($name, null, $cols, $schema);
    }

    /**
     * Performs a validation on the select query before passing back to the parent class.
     * Ensures that only columns from the primary \Micro\Database\Table\TableAbstract are returned in the result.
     * @return null|string This object as a SELECT string (or null if a string cannot be produced)
     * @throws DatabaseSelect\Exception
     * @throws Exception
     */
    public function assemble()
    {
        $fields  = $this->getPart(Select::COLUMNS);
        $primary = $this->_info[TableAbstract::NAME];
        $schema  = $this->_info[TableAbstract::SCHEMA];


        if (count($this->_parts[self::UNION]) == 0) {

            // If no fields are specified we assume all fields from primary table
            if (!count($fields)) {
                $this->from($primary, self::SQL_WILDCARD, $schema);
                $fields = $this->getPart(Select::COLUMNS);
            }

            $from = $this->getPart(Select::FROM);

            if ($this->_integrityCheck !== false) {
                foreach ($fields as $columnEntry) {
                    list($table, $column) = $columnEntry;

                    // Check each column to ensure it only references the primary table
                    if ($column) {
                        if (!isset($from[$table]) || $from[$table]['tableName'] != $primary) {
                            throw new Exception('Select query cannot join with another table');
                        }
                    }
                }
            }
        }

        return parent::assemble();
    }
}