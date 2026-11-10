<?php

namespace Micro\Database\Table\Rowset;

use Micro\Database\Table\Row\Exception as RowException;
use Micro\Database\Table\TableAbstract;

abstract class RowsetAbstract implements \SeekableIterator, \Countable, \ArrayAccess
{
    /**
     * The original data for each row.
     *
     * @var array
     */
    protected $_data = array();

    /**
     * \Micro\Database\Table\TableAbstract object.
     *
     * @var \Micro\Database\Table\TableAbstract
     */
    protected $_table;

    /**
     * Connected is true if we have a reference to a live
     * \Micro\Database\Table\TableAbstract object.
     * This is false after the Rowset has been deserialized.
     *
     * @var boolean
     */
    protected $_connected = true;

    /**
     * \Micro\Database\Table\TableAbstract class name.
     *
     * @var string
     */
    protected $_tableClass;

    /**
     * \Micro\Database\Table\Row\RowAbstract class name.
     *
     * @var string
     */
    protected $_rowClass = 'Micro\Database\Table\Row';

    /**
     * Iterator pointer.
     *
     * @var integer
     */
    protected $_pointer = 0;

    /**
     * How many data rows there are.
     *
     * @var integer
     */
    protected $_count;

    /**
     * Collection of instantiated \Micro\Database\Table\Row\RowAbstract objects.
     *
     * @var array
     */
    protected $_rows = array();

    /**
     * @var boolean
     */
    protected $_stored = false;

    /**
     * @var boolean
     */
    protected $_readOnly = false;

    /**
     * Constructor.
     *
     * @param array $config
     * @throws RowException
     */
    public function __construct(array $config)
    {
        if (isset($config['table'])) {
            $this->_table      = $config['table'];
            $this->_tableClass = get_class($this->_table);
        }
        if (isset($config['rowClass'])) {
            $this->_rowClass   = $config['rowClass'];
        }
        if (!class_exists($this->_rowClass)) {
            throw new RowException("Class '{$this->_rowClass}' does not exists");
        }
        if (isset($config['data'])) {
            $this->_data       = $config['data'];
        }
        if (isset($config['readOnly'])) {
            $this->_readOnly   = $config['readOnly'];
        }
        if (isset($config['stored'])) {
            $this->_stored     = $config['stored'];
        }

        // set the count of rows
        $this->_count = count($this->_data);

        $this->init();
    }

    /**
     * Store data, class names, and state in serialized object
     *
     * @return array
     */
    public function __sleep()
    {
        return array('_data', '_tableClass', '_rowClass', '_pointer', '_count', '_rows', '_stored',
                     '_readOnly');
    }

    /**
     * Setup to do on wakeup.
     * A de-serialized Rowset should not be assumed to have access to a live
     * database connection, so set _connected = false.
     *
     * @return void
     */
    public function __wakeup()
    {
        $this->_connected = false;
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
     * Return the connected state of the rowset.
     *
     * @return boolean
     */
    public function isConnected()
    {
        return $this->_connected;
    }

    /**
     * Returns the table object, or null if this is disconnected rowset
     *
     * @return \Micro\Database\Table\TableAbstract
     */
    public function getTable()
    {
        return $this->_table;
    }

    /**
     * Set the table object, to re-establish a live connection
     * to the database for a Rowset that has been de-serialized.
     *
     * @param \Micro\Database\Table\TableAbstract $table
     * @return boolean
     * @throws \Micro\Database\Table\Row\Exception
     */
    public function setTable(TableAbstract $table)
    {
        $this->_table = $table;
        $this->_connected = false;
        // @todo This works only if we have iterated through
        // the result set once to instantiate the rows.
        foreach ($this as $row) {
            $connected = $row->setTable($table);
            if ($connected == true) {
                $this->_connected = true;
            }
        }
        return $this->_connected;
    }

    /**
     * Query the class name of the Table object for which this
     * Rowset was created.
     *
     * @return string
     */
    public function getTableClass()
    {
        return $this->_tableClass;
    }

    /**
     * Rewind the Iterator to the first element.
     * Similar to the reset() function for arrays in PHP.
     * Required by interface Iterator.
     *
     * @return \Micro\Database\Table\Rowset\RowsetAbstract Fluent interface.
     */
    #[\ReturnTypeWillChange]
    public function rewind()
    {
        $this->_pointer = 0;
        return $this;
    }

    /**
     * Return the current element.
     * Similar to the current() function for arrays in PHP
     * Required by interface Iterator.
     *
     * @return \Micro\Database\Table\Row\RowAbstract current element from the collection
     */
    #[\ReturnTypeWillChange]
    public function current()
    {
        if ($this->valid() === false) {
            return null;
        }

        // return the row object
        return $this->_loadAndReturnRow($this->_pointer);
    }

    /**
     * Return the identifying key of the current element.
     * Similar to the key() function for arrays in PHP.
     * Required by interface Iterator.
     *
     * @return int
     */
    #[\ReturnTypeWillChange]
    public function key()
    {
        return $this->_pointer;
    }

    /**
     * Move forward to next element.
     * Similar to the next() function for arrays in PHP.
     * Required by interface Iterator.
     *
     * @return void
     */
    #[\ReturnTypeWillChange]
    public function next()
    {
        ++$this->_pointer;
    }

    /**
     * Check if there is a current element after calls to rewind() or next().
     * Used to check if we've iterated to the end of the collection.
     * Required by interface Iterator.
     *
     * @return bool False if there's nothing more to iterate over
     */
    #[\ReturnTypeWillChange]
    public function valid()
    {
        return $this->_pointer >= 0 && $this->_pointer < $this->_count;
    }

    /**
     * Returns the number of elements in the collection.
     *
     * Implements Countable::count()
     *
     * @return int
     */
    #[\ReturnTypeWillChange]
    public function count()
    {
        return $this->_count;
    }

    /**
     * Take the Iterator to position $position
     * Required by interface SeekableIterator.
     *
     * @param int $position the position to seek to
     * @return \Micro\Database\Table\Rowset\RowsetAbstract
     * @throws \Micro\Database\Table\Rowset\Exception
     */
    #[\ReturnTypeWillChange]
    public function seek($position)
    {
        $position = (int) $position;
        if ($position < 0 || $position >= $this->_count) {
            throw new Exception("Illegal index $position");
        }
        $this->_pointer = $position;
        return $this;
    }

    /**
     * Check if an offset exists
     * Required by the ArrayAccess implementation
     *
     * @param string $offset
     * @return boolean
     */
    #[\ReturnTypeWillChange]
    public function offsetExists($offset)
    {
        return isset($this->_data[(int) $offset]);
    }

    /**
     * Get the row for the given offset
     * Required by the ArrayAccess implementation
     *
     * @param string $offset
     * @return RowsetAbstract
     * @throws Exception
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        $offset = (int) $offset;
        if ($offset < 0 || $offset >= $this->_count) {
            throw new Exception("Illegal index $offset");
        }
        $this->_pointer = $offset;

        return $this->current();
    }

    /**
     * Does nothing
     * Required by the ArrayAccess implementation
     *
     * @param string $offset
     * @param mixed $value
     */
    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value)
    {
    }

    /**
     * Does nothing
     * Required by the ArrayAccess implementation
     *
     * @param string $offset
     */
    #[\ReturnTypeWillChange]
    public function offsetUnset($offset)
    {
    }

    /**
     * Returns a \Micro\Database\Table\Row\RowAbstract from a known position into the Iterator
     *
     * @param int $position the position of the row expected
     * @param bool $seek wether or not seek the iterator to that position after
     * @return \Micro\Database\Table\Row\RowAbstract
     * @throws \Micro\Database\Table\Rowset\Exception
     */
    public function getRow($position, $seek = false)
    {
        try {
            $row = $this->_loadAndReturnRow($position);
        } catch (Exception $e) {
            throw new Exception('No row could be found at position ' . (int) $position, 0, $e);
        }

        if ($seek == true) {
            $this->seek($position);
        }

        return $row;
    }

    /**
     * Returns all data as an array.
     *
     * Updates the $_data property with current row object values.
     *
     * @return array
     */
    public function toArray()
    {
        // @todo This works only if we have iterated through
        // the result set once to instantiate the rows.
        foreach ($this->_rows as $i => $row) {
            $this->_data[$i] = $row->toArray();
        }
        return $this->_data;
    }

    protected function _loadAndReturnRow($position)
    {
        if (!isset($this->_data[$position])) {
            throw new Exception("Data for provided position does not exist");
        }

        // do we already have a row object for this position?
        if (empty($this->_rows[$position])) {
            if (!class_exists($this->_rowClass)) {
                throw new Exception('Class "' . $this->_rowClass . '" does not exists');
            }
            $this->_rows[$position] = new $this->_rowClass(
                array(
                    'table'    => $this->_table,
                    'data'     => $this->_data[$position],
                    'stored'   => $this->_stored,
                    'readOnly' => $this->_readOnly
                )
            );
        }

        // return the row object
        return $this->_rows[$position];
    }
}