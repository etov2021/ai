<?php

namespace Micro\Database\Table\Row;

use Micro\Database\Database;
use Micro\Database\Table\TableAbstract;
use Micro\Database\Table\Select as TableSelect;

abstract class RowAbstract implements \ArrayAccess, \IteratorAggregate
{
    /**
     * The data for each column in the row (column_name => value).
     * The keys must match the physical names of columns in the
     * table for which this row is defined.
     *
     * @var array
     */
    protected $_data = array();

    /**
     * This is set to a copy of $_data when the data is fetched from
     * a database, specified as a new tuple in the constructor, or
     * when dirty data is posted to the database with save().
     *
     * @var array
     */
    protected $_cleanData = array();

    /**
     * Tracks columns where data has been updated. Allows more specific insert and
     * update operations.
     *
     * @var array
     */
    protected $_modifiedFields = array();

    /**
     * \Micro\Database\Table\TableAbstract parent class or instance.
     *
     * @var \Micro\Database\Table\TableAbstract
     */
    protected $_table = null;

    /**
     * Connected is true if we have a reference to a live
     * \Micro\Database\Table\TableAbstract object.
     * This is false after the Rowset has been deserialized.
     *
     * @var boolean
     */
    protected $_connected = true;

    /**
     * A row is marked read only if it contains columns that are not physically represented within
     * the database schema (e.g. evaluated columns/\Micro\Database\Expr columns). This can also be passed
     * as a run-time config options as a means of protecting row data.
     *
     * @var boolean
     */
    protected $_readOnly = false;

    /**
     * Name of the class of the \Micro\Database\Table\TableAbstract object.
     *
     * @var string
     */
    protected $_tableClass = null;

    /**
     * Primary row key(s).
     *
     * @var array
     */
    protected $_primary;

    /**
     * Constructor.
     *
     * Supported params for $config are:-
     * - table       = class name or object of type \Micro\Database\Table\TableAbstract
     * - data        = values of columns in this row.
     *
     * @param  array $config OPTIONAL Array of user-specified config options.
     * @throws Exception
     */
    public function __construct(array $config = array())
    {
        if (isset($config['table']) && $config['table'] instanceof TableAbstract) {
            $this->_table = $config['table'];
            $this->_tableClass = get_class($this->_table);
        } elseif ($this->_tableClass !== null) {
            $this->_table = $this->_getTableFromString($this->_tableClass);
        }

        if (isset($config['data'])) {
            if (!is_array($config['data'])) {
                throw new Exception('Data must be an array');
            }
            $this->_data = $config['data'];
        }
        if (isset($config['stored']) && $config['stored'] === true) {
            $this->_cleanData = $this->_data;
        }

        if (isset($config['readOnly']) && $config['readOnly'] === true) {
            $this->setReadOnly(true);
        }

        // Retrieve primary keys from table schema
        $table = $this->_getTable();
        if ($table) {
            $info = $table->info();
            $this->_primary = (array) $info['primary'];
        }

        $this->init();
    }

    /**
     * Transform a column name from the user-specified form
     * to the physical form used in the database.
     * You can override this method in a custom Row class
     * to implement column name mappings, for example inflection.
     *
     * @param string $columnName Column name given.
     * @return string The column name after transformation applied (none by default).
     * @throws Exception if the $columnName is not a string.
     */
    protected function _transformColumn($columnName)
    {
        if (!is_string($columnName)) {
            throw new Exception('Specified column is not a string');
        }
        // Perform no transformation by default
        return $columnName;
    }

    /**
     * Retrieve row field value
     *
     * @param  string $columnName The user-specified column name.
     * @return string             The corresponding column value.
     * @throws Exception if the $columnName is not a column in the row.
     */
    public function __get($columnName)
    {
        $columnName = $this->_transformColumn($columnName);
        if (!array_key_exists($columnName, $this->_data)) {
            throw new Exception("Specified column \"$columnName\" is not in the row");
        }
        return $this->_data[$columnName];
    }

    /**
     * Set row field value
     *
     * @param  string $columnName The column key.
     * @param  mixed  $value      The value for the property.
     * @return void
     * @throws Exception
     */
    public function __set($columnName, $value)
    {
        $columnName = $this->_transformColumn($columnName);
        if (!array_key_exists($columnName, $this->_data)) {
            throw new Exception("Specified column \"$columnName\" is not in the row");
        }
        $this->_data[$columnName] = $value;
        $this->_modifiedFields[$columnName] = true;
    }

    /**
     * Unset row field value
     *
     * @param  string $columnName The column key.
     * @return \Micro\Database\Table\Row\RowAbstract
     * @throws Exception
     */
    public function __unset($columnName)
    {
        $columnName = $this->_transformColumn($columnName);
        if (!array_key_exists($columnName, $this->_data)) {
            throw new Exception("Specified column \"$columnName\" is not in the row");
        }
        if ($this->isConnected() && in_array($columnName, $this->_table->info('primary'))) {
            throw new Exception("Specified column \"$columnName\" is a primary key and should not be unset");
        }
        unset($this->_data[$columnName]);
        return $this;
    }

    /**
     * Test existence of row field
     *
     * @param  string  $columnName   The column key.
     * @return boolean
     */
    public function __isset($columnName)
    {
        $columnName = $this->_transformColumn($columnName);
        return array_key_exists($columnName, $this->_data);
    }

    /**
     * Store table, primary key and data in serialized object
     *
     * @return array
     */
    public function __sleep()
    {
        return array('_tableClass', '_primary', '_data', '_cleanData', '_readOnly' ,'_modifiedFields');
    }

    /**
     * Setup to do on wakeup.
     * A de-serialized Row should not be assumed to have access to a live
     * database connection, so set _connected = false.
     *
     * @return void
     */
    public function __wakeup()
    {
        $this->_connected = false;
    }

    /**
     * Proxy to __isset
     * Required by the ArrayAccess implementation
     *
     * @param string $offset
     * @return boolean
     */
    #[\ReturnTypeWillChange]
    public function offsetExists($offset)
    {
        return $this->__isset($offset);
    }

    /**
     * Proxy to __get
     * Required by the ArrayAccess implementation
     *
     * @param string $offset
     * @return string
     */
     #[\ReturnTypeWillChange]
    public function offsetGet($offset)
     {
         return $this->__get($offset);
     }

     /**
      * Proxy to __set
      * Required by the ArrayAccess implementation
      *
      * @param string $offset
      * @param mixed $value
      */
     #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value)
     {
         $this->__set($offset, $value);
     }

    /**
     * Proxy to __unset
     * Required by the ArrayAccess implementation
     *
     * @param string $offset
     * @return RowAbstract|void
     */
     #[\ReturnTypeWillChange]
    public function offsetUnset($offset)
     {
         return $this->__unset($offset);
     }

    /**
     * Initialize object
     *
     * Called from {@link __construct()} as final step of object instantiation.
     *
     * @return void
     */
    public function init()
    {
    }

    /**
     * Returns the table object, or null if this is disconnected row
     *
     * @return \Micro\Database\Table\TableAbstract|null
     */
    public function getTable()
    {
        return $this->_table;
    }

    /**
     * Set the table object, to re-establish a live connection
     * to the database for a Row that has been de-serialized.
     *
     * @param \Micro\Database\Table\TableAbstract $table
     * @return boolean
     * @throws Exception
     */
    public function setTable(?TableAbstract $table = null)
    {
        if ($table == null) {
            $this->_table = null;
            $this->_connected = false;
            return false;
        }

        $tableClass = get_class($table);
        if (! $table instanceof $this->_tableClass) {
            throw new Exception("The specified Table is of class $tableClass, expecting class to be instance of $this->_tableClass");
        }

        $this->_table = $table;
        $this->_tableClass = $tableClass;

        $info = $this->_table->info();

        if ($info['cols'] != array_keys($this->_data)) {
            throw new Exception('The specified Table does not have the same columns as the Row');
        }

        if (! array_intersect((array) $this->_primary, $info['primary']) == (array) $this->_primary) {
            throw new Exception("The specified Table '$tableClass' does not have the same primary key as the Row");
        }

        $this->_connected = true;
        return true;
    }

    /**
     * Query the class name of the Table object for which this
     * Row was created.
     *
     * @return string
     */
    public function getTableClass()
    {
        return $this->_tableClass;
    }

    /**
     * Test the connected status of the row.
     *
     * @return boolean
     */
    public function isConnected()
    {
        return $this->_connected;
    }

    /**
     * Test the read-only status of the row.
     *
     * @return boolean
     */
    public function isReadOnly()
    {
        return $this->_readOnly;
    }

    /**
     * Set the read-only status of the row.
     *
     * @param boolean $flag
     * @return boolean
     */
    public function setReadOnly($flag)
    {
        $this->_readOnly = (bool) $flag;
    }

    /**
     * Returns an instance of the parent table's \Micro\Database\Table\Select object.
     *
     * @return \Micro\Database\Table\Select
     */
    public function select()
    {
        return $this->getTable()->select();
    }

    /**
     * Saves the properties to the database.
     *
     * This performs an intelligent insert/update, and reloads the
     * properties with fresh data from the table on success.
     *
     * @return mixed The primary key value(s), as an associative array if the
     *     key is compound, or a scalar if the key is single-column.
     */
    public function save()
    {
        /**
         * If the _cleanData array is empty,
         * this is an INSERT of a new row.
         * Otherwise it is an UPDATE.
         */
        if (empty($this->_cleanData)) {
            return $this->_doInsert();
        } else {
            return $this->_doUpdate();
        }
    }

    /**
     * @return mixed The primary key value(s), as an associative array if the
     *     key is compound, or a scalar if the key is single-column.
     * @throws Exception
     */
    protected function _doInsert()
    {
        /**
         * A read-only row cannot be saved.
         */
        if ($this->_readOnly === true) {
            throw new Exception('This row has been marked read-only');
        }

        /**
         * Run pre-INSERT logic
         */
        $this->_insert();

        /**
         * Execute the INSERT (this may throw an exception)
         */
        $data = array_intersect_key($this->_data, $this->_modifiedFields);
        $primaryKey = $this->_getTable()->insert($data);

        /**
         * Normalize the result to an array indexed by primary key column(s).
         * The table insert() method may return a scalar.
         */
        if (is_array($primaryKey)) {
            $newPrimaryKey = $primaryKey;
        } else {
            $tempPrimaryKey = (array) $this->_primary;
            $newPrimaryKey = array(current($tempPrimaryKey) => $primaryKey);
        }

        /**
         * Save the new primary key value in _data.  The primary key may have
         * been generated by a sequence or auto-increment mechanism, and this
         * merge should be done before the _postInsert() method is run, so the
         * new values are available for logging, etc.
         */
        $this->_data = array_merge($this->_data, $newPrimaryKey);

        /**
         * Run post-INSERT logic
         */
        $this->_postInsert();

        /**
         * Update the _cleanData to reflect that the data has been inserted.
         */
        $this->_refresh();

        return $primaryKey;
    }

    /**
     * @return mixed The primary key value(s), as an associative array if the
     *     key is compound, or a scalar if the key is single-column.
     * @throws Exception
     */
    protected function _doUpdate()
    {
        /**
         * A read-only row cannot be saved.
         */
        if ($this->_readOnly === true) {
            throw new Exception('This row has been marked read-only');
        }

        /**
         * Get expressions for a WHERE clause
         * based on the primary key value(s).
         */
        $where = $this->_getWhereQuery(false);

        /**
         * Run pre-UPDATE logic
         */
        $this->_update();

        /**
         * Compare the data to the modified fields array to discover
         * which columns have been changed.
         */
        $diffData = array_intersect_key($this->_data, $this->_modifiedFields);

        /**
         * Were any of the changed columns part of the primary key?
         */
        $pkDiffData = array_intersect_key($diffData, array_flip((array)$this->_primary));

        /**
         * Execute cascading updates against dependent tables.
         * Do this only if primary key value(s) were changed.
         */
        if (count($pkDiffData) > 0) {
            $depTables = $this->_getTable()->getDependentTables();
            if (!empty($depTables)) {
                $pkNew = $this->_getPrimaryKey(true);
                $pkOld = $this->_getPrimaryKey(false);
                foreach ($depTables as $tableClass) {
                    $t = $this->_getTableFromString($tableClass);
                    $t->_cascadeUpdate($this->getTableClass(), $pkOld, $pkNew);
                }
            }
        }

        /**
         * Execute the UPDATE (this may throw an exception)
         * Do this only if data values were changed.
         * Use the $diffData variable, so the UPDATE statement
         * includes SET terms only for data values that changed.
         */
        if (count($diffData) > 0) {
            $this->_getTable()->update($diffData, $where);
        }

        /**
         * Run post-UPDATE logic.  Do this before the _refresh()
         * so the _postUpdate() function can tell the difference
         * between changed data and clean (pre-changed) data.
         */
        $this->_postUpdate();

        /**
         * Refresh the data just in case triggers in the RDBMS changed
         * any columns.  Also this resets the _cleanData.
         */
        $this->_refresh();

        /**
         * Return the primary key value(s) as an array
         * if the key is compound or a scalar if the key
         * is a scalar.
         */
        $primaryKey = $this->_getPrimaryKey(true);
        if (count($primaryKey) == 1) {
            return current($primaryKey);
        }

        return $primaryKey;
    }

    /**
     * Deletes existing rows.
     * @return int The number of rows deleted.
     * @throws Exception
     */
    public function delete()
    {
        /**
         * A read-only row cannot be deleted.
         */
        if ($this->_readOnly === true) {
            throw new Exception('This row has been marked read-only');
        }

        $where = $this->_getWhereQuery();

        /**
         * Execute pre-DELETE logic
         */
        $this->_delete();

        /**
         * Execute cascading deletes against dependent tables
         */
        $depTables = $this->_getTable()->getDependentTables();
        if (!empty($depTables)) {
            $pk = $this->_getPrimaryKey();
            foreach ($depTables as $tableClass) {
                $t = $this->_getTableFromString($tableClass);
                $t->_cascadeDelete($this->getTableClass(), $pk);
            }
        }

        /**
         * Execute the DELETE (this may throw an exception)
         */
        $result = $this->_getTable()->delete($where);

        /**
         * Execute post-DELETE logic
         */
        $this->_postDelete();

        /**
         * Reset all fields to null to indicate that the row is not there
         */
        $this->_data = array_combine(
            array_keys($this->_data),
            array_fill(0, count($this->_data), null)
        );

        return $result;
    }

    #[\ReturnTypeWillChange]
    public function getIterator()
    {
        return new \ArrayIterator((array) $this->_data);
    }

    /**
     * Returns the column/value data as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return (array)$this->_data;
    }

    /**
     * Sets all data in the row from an array.
     *
     * @param  array $data
     * @return \Micro\Database\Table\Row\RowAbstract Provides a fluent interface
     */
    public function setFromArray(array $data)
    {
        $data = array_intersect_key($data, $this->_data);

        foreach ($data as $columnName => $value) {
            $this->__set($columnName, $value);
        }

        return $this;
    }

    /**
     * Refreshes properties from the database.
     *
     * @return void
     */
    public function refresh()
    {
        return $this->_refresh();
    }

    /**
     * Retrieves an instance of the parent table.
     * @return TableAbstract
     * @throws Exception
     */
    protected function _getTable()
    {
        if (!$this->_connected) {
            throw new Exception('Cannot save a Row unless it is connected');
        }
        return $this->_table;
    }

    /**
     * Retrieves an associative array of primary keys.
     *
     * @param bool $useDirty
     * @return array
     * @throws Exception
     */
    protected function _getPrimaryKey($useDirty = true)
    {
        if (!is_array($this->_primary)) {
            throw new Exception("The primary key must be set as an array");
        }

        $primary = array_flip($this->_primary);
        if ($useDirty) {
            $array = array_intersect_key($this->_data, $primary);
        } else {
            $array = array_intersect_key($this->_cleanData, $primary);
        }
        if (count($primary) != count($array)) {
            throw new Exception("The specified Table '$this->_tableClass' does not have the same primary key as the Row");
        }
        return $array;
    }

    /**
     * Retrieves an associative array of primary keys.
     *
     * @param bool $useDirty
     * @return array
     */
    public function getPrimaryKey($useDirty = true)
    {
        return $this->_getPrimaryKey($useDirty);
    }

    /**
     * Constructs where statement for retrieving row(s).
     *
     * @param bool $useDirty
     * @return array
     */
    protected function _getWhereQuery($useDirty = true)
    {
        $where = array();
        $db = $this->_getTable()->getAdapter();
        $primaryKey = $this->_getPrimaryKey($useDirty);
        $info = $this->_getTable()->info();
        $metadata = $info[TableAbstract::METADATA];

        // retrieve recently updated row using primary keys
        $where = array();
        foreach ($primaryKey as $column => $value) {
            $tableName = $db->quoteIdentifier($info[TableAbstract::NAME], true);
            $type = $metadata[$column]['DATA_TYPE'];
            $columnName = $db->quoteIdentifier($column, true);
            $where[] = $db->quoteInto("{$tableName}.{$columnName} = ?", $value, $type);
        }
        return $where;
    }

    /**
     * Refreshes properties from the database.
     *
     */
    protected function _refresh()
    {
        $where = $this->_getWhereQuery();
        $row = $this->_getTable()->fetchRow($where);

        if (null === $row) {
            throw new Exception('Cannot refresh row as parent is missing');
        }

        $this->_data = $row->toArray();
        $this->_cleanData = $this->_data;
        $this->_modifiedFields = array();
    }

    /**
     * Allows pre-insert logic to be applied to row.
     * Subclasses may override this method.
     *
     * @return void
     */
    protected function _insert()
    {
    }

    /**
     * Allows post-insert logic to be applied to row.
     * Subclasses may override this method.
     *
     * @return void
     */
    protected function _postInsert()
    {
    }

    /**
     * Allows pre-update logic to be applied to row.
     * Subclasses may override this method.
     *
     * @return void
     */
    protected function _update()
    {
    }

    /**
     * Allows post-update logic to be applied to row.
     * Subclasses may override this method.
     *
     * @return void
     */
    protected function _postUpdate()
    {
    }

    /**
     * Allows pre-delete logic to be applied to row.
     * Subclasses may override this method.
     *
     * @return void
     */
    protected function _delete()
    {
    }

    /**
     * Allows post-delete logic to be applied to row.
     * Subclasses may override this method.
     *
     * @return void
     */
    protected function _postDelete()
    {
    }

    /**
     * Prepares a table reference for lookup.
     *
     * Ensures all reference keys are set and properly formatted.
     *
     * @param \Micro\Database\Table\TableAbstract $dependentTable
     * @param \Micro\Database\Table\TableAbstract $parentTable
     * @param string                 $ruleKey
     * @return array
     */
    protected function _prepareReference(TableAbstract $dependentTable, TableAbstract $parentTable, $ruleKey)
    {
        $parentTableName = get_class($parentTable);
        $map = $dependentTable->getReference($parentTableName, $ruleKey);

        if (!isset($map[TableAbstract::REF_COLUMNS])) {
            $parentInfo = $parentTable->info();
            $map[TableAbstract::REF_COLUMNS] = array_values((array) $parentInfo['primary']);
        }

        $map[TableAbstract::COLUMNS] = (array) $map[TableAbstract::COLUMNS];
        $map[TableAbstract::REF_COLUMNS] = (array) $map[TableAbstract::REF_COLUMNS];

        return $map;
    }

    /**
     * Query a dependent table to retrieve rows matching the current row.
     *
     * @param string|TableAbstract  $dependentTable
     * @param string                         $ruleKey
     * @param \Micro\Database\Table\Select   $select
     * @return \Micro\Database\Table\Rowset\RowsetAbstract  Query result from $dependentTable
     * @throws Exception If $dependentTable is not a table or is not loadable.
     */
    public function findDependentRowset($dependentTable, $ruleKey = null, ?TableSelect $select = null)
    {
        $db = $this->_getTable()->getAdapter();

        if (is_string($dependentTable)) {
            $dependentTable = $this->_getTableFromString($dependentTable);
        }

        if (!$dependentTable instanceof TableAbstract) {
            $type = gettype($dependentTable);
            if ($type == 'object') {
                $type = get_class($dependentTable);
            }
            throw new Exception("Dependent table must be a \\Micro\\Database\\Table\\TableAbstract, but it is $type");
        }

        if ($select === null) {
            $select = $dependentTable->select();
        } else {
            $select->setTable($dependentTable);
        }

        $map = $this->_prepareReference($dependentTable, $this->_getTable(), $ruleKey);

        for ($i = 0; $i < count($map[TableAbstract::COLUMNS]); ++$i) {
            $parentColumnName = $db->foldCase($map[TableAbstract::REF_COLUMNS][$i]);
            $value = $this->_data[$parentColumnName];
            // Use adapter from dependent table to ensure correct query construction
            $dependentDb = $dependentTable->getAdapter();
            $dependentColumnName = $dependentDb->foldCase($map[TableAbstract::COLUMNS][$i]);
            $dependentColumn = $dependentDb->quoteIdentifier($dependentColumnName, true);
            $dependentInfo = $dependentTable->info();
            $type = $dependentInfo[TableAbstract::METADATA][$dependentColumnName]['DATA_TYPE'];
            $select->where("$dependentColumn = ?", $value, $type);
        }

        return $dependentTable->fetchAll($select);
    }

    /**
     * Query a parent table to retrieve the single row matching the current row.
     *
     * @param string|\Micro\Database\Table\TableAbstract $parentTable
     * @param string                                         $ruleKey
     * @param \Micro\Database\Table\Select                   OPTIONAL $select
     * @return \Micro\Database\Table\Rowset\RowsetAbstract   Query result from $parentTable
     * @throws Exception If $parentTable is not a table or is not loadable.
     */
    public function findParentRow($parentTable, $ruleKey = null, ?TableSelect $select = null)
    {
        $db = $this->_getTable()->getAdapter();

        if (is_string($parentTable)) {
            $parentTable = $this->_getTableFromString($parentTable);
        }

        if (!$parentTable instanceof TableAbstract) {
            $type = gettype($parentTable);
            if ($type == 'object') {
                $type = get_class($parentTable);
            }
            throw new Exception("Parent table must be a \Micro\Database\Table\TableAbstract, but it is $type");
        }

        if ($select === null) {
            $select = $parentTable->select();
        } else {
            $select->setTable($parentTable);
        }

        $map = $this->_prepareReference($this->_getTable(), $parentTable, $ruleKey);

        // iterate the map, creating the proper wheres
        for ($i = 0; $i < count($map[TableAbstract::COLUMNS]); ++$i) {
            $dependentColumnName = $db->foldCase($map[TableAbstract::COLUMNS][$i]);
            $value = $this->_data[$dependentColumnName];
            // Use adapter from parent table to ensure correct query construction
            $parentDb = $parentTable->getAdapter();
            $parentColumnName = $parentDb->foldCase($map[TableAbstract::REF_COLUMNS][$i]);
            $parentColumn = $parentDb->quoteIdentifier($parentColumnName, true);
            $parentInfo = $parentTable->info();

            // determine where part
            $type     = $parentInfo[TableAbstract::METADATA][$parentColumnName]['DATA_TYPE'];
            $nullable = $parentInfo[TableAbstract::METADATA][$parentColumnName]['NULLABLE'];
            if ($value === null && $nullable == true) {
                $select->where("$parentColumn IS NULL");
            } elseif ($value === null && $nullable == false) {
                return null;
            } else {
                $select->where("$parentColumn = ?", $value, $type);
            }

        }

        return $parentTable->fetchRow($select);
    }

    /**
     * @param  string|\Micro\Database\Table\TableAbstract  $matchTable
     * @param  string|\Micro\Database\Table\TableAbstract  $intersectionTable
     * @param  string                          $callerRefRule
     * @param  string                          $matchRefRule
     * @param  \Micro\Database\Table\Select    $select
     * @return \Micro\Database\Table\Rowset\RowsetAbstract Query result from $matchTable
     * @throws Exception If $matchTable or $intersectionTable is not a table class or is not loadable.
     */
    public function findManyToManyRowset($matchTable, $intersectionTable, $callerRefRule = null,
                                         $matchRefRule = null, ?TableSelect $select = null)
    {
        $db = $this->_getTable()->getAdapter();

        if (is_string($intersectionTable)) {
            $intersectionTable = $this->_getTableFromString($intersectionTable);
        }

        if (!$intersectionTable instanceof TableAbstract) {
            $type = gettype($intersectionTable);
            if ($type == 'object') {
                $type = get_class($intersectionTable);
            }
            throw new Exception("Intersection table must be a TableAbstract, but it is $type");
        }

        if (is_string($matchTable)) {
            $matchTable = $this->_getTableFromString($matchTable);
        }

        if (! $matchTable instanceof TableAbstract) {
            $type = gettype($matchTable);
            if ($type == 'object') {
                $type = get_class($matchTable);
            }
            throw new Exception("Match table must be a \\Micro\\Database\\Table\\TableAbstract, but it is $type");
        }

        if ($select === null) {
            $select = $matchTable->select();
        } else {
            $select->setTable($matchTable);
        }

        // Use adapter from intersection table to ensure correct query construction
        $interInfo = $intersectionTable->info();
        $interDb   = $intersectionTable->getAdapter();
        $interName = $interInfo['name'];
        $interSchema = isset($interInfo['schema']) ? $interInfo['schema'] : null;
        $matchInfo = $matchTable->info();
        $matchName = $matchInfo['name'];
        $matchSchema = isset($matchInfo['schema']) ? $matchInfo['schema'] : null;

        $matchMap = $this->_prepareReference($intersectionTable, $matchTable, $matchRefRule);

        $joinCond = array();
        for ($i = 0; $i < count($matchMap[TableAbstract::COLUMNS]); ++$i) {
            $interCol = $interDb->quoteIdentifier('i' . '.' . $matchMap[TableAbstract::COLUMNS][$i], true);
            $matchCol = $interDb->quoteIdentifier('m' . '.' . $matchMap[TableAbstract::REF_COLUMNS][$i], true);
            $joinCond[] = "$interCol = $matchCol";
        }
        $joinCond = implode(' AND ', $joinCond);

        $select->from(array('i' => $interName), array(), $interSchema)
               ->joinInner(array('m' => $matchName), $joinCond, TableSelect::SQL_WILDCARD, $matchSchema)
               ->setIntegrityCheck(false);

        $callerMap = $this->_prepareReference($intersectionTable, $this->_getTable(), $callerRefRule);

        for ($i = 0; $i < count($callerMap[TableAbstract::COLUMNS]); ++$i) {
            $callerColumnName = $db->foldCase($callerMap[TableAbstract::REF_COLUMNS][$i]);
            $value = $this->_data[$callerColumnName];
            $interColumnName = $interDb->foldCase($callerMap[TableAbstract::COLUMNS][$i]);
            $interCol = $interDb->quoteIdentifier("i.$interColumnName", true);
            $interInfo = $intersectionTable->info();
            $type = $interInfo[TableAbstract::METADATA][$interColumnName]['DATA_TYPE'];
            $select->where($interDb->quoteInto("$interCol = ?", $value, $type));
        }

        $stmt = $select->query();

        $config = array(
            'table'    => $matchTable,
            'data'     => $stmt->fetchAll(Database::FETCH_ASSOC),
            'rowClass' => $matchTable->getRowClass(),
            'readOnly' => false,
            'stored'   => true
        );

        $rowsetClass = $matchTable->getRowsetClass();
        if (!class_exists($rowsetClass)) {
            throw new Exception("Class '{$rowsetClass}' does not exists");
        }
        $rowset = new $rowsetClass($config);
        return $rowset;
    }

    /**
     * Turn magic function calls into non-magic function calls
     * to the above methods.
     *
     * @param string $method
     * @param array $args OPTIONAL \Micro\Database\Table\Select query modifier
     * @return \Micro\Database\Table\Row\RowAbstract|\Micro\Database\Table\Rowset\RowsetAbstract
     * @throws Exception If an invalid method is called.
     */
    public function __call($method, array $args)
    {
        $matches = array();

        if (count($args) && $args[0] instanceof TableSelect) {
            $select = $args[0];
        } else {
            $select = null;
        }

        /**
         * Recognize methods for Has-Many cases:
         * findParent<Class>()
         * findParent<Class>By<Rule>()
         * Use the non-greedy pattern repeat modifier e.g. \w+?
         */
        if (preg_match('/^findParent(\w+?)(?:By(\w+))?$/', $method, $matches)) {
            $class    = $matches[1];
            $ruleKey1 = isset($matches[2]) ? $matches[2] : null;
            return $this->findParentRow($class, $ruleKey1, $select);
        }

        /**
         * Recognize methods for Many-to-Many cases:
         * find<Class1>Via<Class2>()
         * find<Class1>Via<Class2>By<Rule>()
         * find<Class1>Via<Class2>By<Rule1>And<Rule2>()
         * Use the non-greedy pattern repeat modifier e.g. \w+?
         */
        if (preg_match('/^find(\w+?)Via(\w+?)(?:By(\w+?)(?:And(\w+))?)?$/', $method, $matches)) {
            $class    = $matches[1];
            $viaClass = $matches[2];
            $ruleKey1 = isset($matches[3]) ? $matches[3] : null;
            $ruleKey2 = isset($matches[4]) ? $matches[4] : null;
            return $this->findManyToManyRowset($class, $viaClass, $ruleKey1, $ruleKey2, $select);
        }

        /**
         * Recognize methods for Belongs-To cases:
         * find<Class>()
         * find<Class>By<Rule>()
         * Use the non-greedy pattern repeat modifier e.g. \w+?
         */
        if (preg_match('/^find(\w+?)(?:By(\w+))?$/', $method, $matches)) {
            $class    = $matches[1];
            $ruleKey1 = isset($matches[2]) ? $matches[2] : null;
            return $this->findDependentRowset($class, $ruleKey1, $select);
        }

        throw new Exception("Unrecognized method '$method()'");
    }


    /**
     * _getTableFromString
     *
     * @param string $tableName
     * @return \Micro\Database\Table\TableAbstract
     */
    protected function _getTableFromString($tableName)
    {
        return TableAbstract::getTableFromString($tableName, $this->_table);
    }
}