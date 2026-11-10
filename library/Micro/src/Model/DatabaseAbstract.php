<?php

namespace Micro\Model;

use Micro\Database\Table\TableAbstract;
use Micro\Database\Expr;
use Micro\Database\Select;
use Micro\Paginator\Adapter\AdapterInterface;
use Micro\Database\Table\Row\RowAbstract;
use Micro\Database\Table\Rowset\RowsetAbstract;
use Micro\Cache;
use Micro\Event;
use Micro\Model\Entity\EntityInterface;
use Micro\Exception\Exception as CoreException;
use Micro\Container\SharedContainer;

abstract class DatabaseAbstract implements AdapterInterface, ModelInterface
{
    /**
     * @var string
     */
    protected $table;

    /**
     * @var TableAbstract
     */
    protected $tableInstance;

    /**
     * @var EntityInterface
     */
    protected $entity;

    /**
     * @var Event\Manager
     */
    protected $eventManager;

    /**
     * @var Cache\Core
     */
    protected static $defaultCache;

    /**
     * @var Cache\Core
     */
    protected $cache;

    /**
     * @var Select
     */
    protected $select;

    /**
     * @var int
     */
    protected static $transactionLevel = 0;

    /**
     * @var bool
     */
    protected $enableLog = \true;

    /**
     * @param TableAbstract $table
     * @param string $entity
     * @param Event\Manager $eventManager
     * @param Cache\Core $cache
     * @throws CoreException
     */
    public function __construct(
        ?TableAbstract $table = \null,
        $entity = \null,
        ?Event\Manager $eventManager = \null,
        ?Cache\Core $cache = \null
    ) {

        if ($table !== \null) {
            $this->tableInstance = $table;
        } else {
            if ($this->table === \null || !\is_string($this->table) || !\class_exists($this->table, \true)) {
                throw new CoreException(\get_class($this) . ' Table is not set or not exists');
            }
            $this->tableInstance = new $this->table;
            if (!$this->tableInstance instanceof TableAbstract) {
                throw new CoreException(\get_class($this) . ' Table is not instanceof ' . TableAbstract::class);
            }
        }

        if ($entity !== \null) {
            $this->entity = $entity;
        }

        $this->eventManager = $eventManager ?: SharedContainer::getInstance()->get('event');
        $this->cache = $cache ?: static::$defaultCache;
    }

    /**
     * @param Cache\Core $defaultCache
     */
    public static function setDefaultCache(?Cache\Core $defaultCache = null)
    {
        static::$defaultCache = $defaultCache;
    }

    /**
     * @return TableAbstract
     */
    public function getTable()
    {
        return $this->tableInstance;
    }

    /**
     * @param array|null $data
     * @return EntityInterface
     * @throws CoreException
     */
    public function createEntity(?array $data = \null)
    {
        if ($this->entity === \null || !\class_exists($this->entity, \true)) {
            throw new CoreException(\get_class($this) . ' Entity is not set or not exists');
        }

        $entity = new $this->entity;

        if (!$entity instanceof EntityInterface) {
            throw new CoreException(get_class($this) . ' Entity is not instanceof ' . EntityInterface::class);
        }

        if ($data !== \null) {
            $entity->setFromArray($data);
        }

        return $entity;
    }

    /**
     * @param string $column
     * @param bool $onlyDependent
     * @return TableAbstract|\null
     */
    public function getTableByColumn($column, $onlyDependent = \false)
    {
        if ($onlyDependent === \false && \in_array($column, $this->tableInstance->info(TableAbstract::COLS))) {
            return $this->tableInstance;
        }

        foreach ($this->tableInstance->getDependentTables() as $dependentTable) {
            $dependentTableInstance = $this->tableInstance->getDependentTableInstance($dependentTable);
            if (\in_array($column, $dependentTableInstance->info(TableAbstract::COLS))) {
                return $dependentTableInstance;
            }
        }

        return \null;
    }

    public function find($id, $reset = \true)
    {
        if ($reset !== \false) {
            $this->resetSelect();
        }

        $ids = (array) $id;

        foreach ($this->tableInstance->info(TableAbstract::PRIMARY) as $field) {

            $id = \array_shift($ids);

            if ($id === \null) {
                break;
            }

            $this->addWhere($field, $id);
        }

        return $this->rowToObject(
            $this->tableInstance->fetchRow($this->getSelect())
        );
    }

    public function addWhere($field, $value = \null)
    {
        $select = $this->getSelect();

        if ($field instanceof Expr) {
            $select->where($field);
            return $this;
        }

        $table = $this->getTableByColumn($field);

        if (!$table instanceof TableAbstract) {
            \trigger_error(\sprintf('addWhere: Field %s not found in model %s tables', $field, \get_class($this)), \E_USER_NOTICE);
            return $this;
        }

        $alias = $table->info(TableAbstract::NAME);

        if ($value === \null) {
            $select->where(new Expr("{$alias}.{$field} IS NULL"));
        } else if (\is_array($value)) {
            $select->where("{$alias}.{$field} IN (?)", $value);
        } else if (\is_string($value) && \preg_match('/(^%|%$)/i', $value)) {
            $select->where("{$alias}.{$field} LIKE ?", $value);
        } else if (\is_scalar($value)) {
            $select->where("{$alias}.{$field} = ?", $value);
        }

        return $this;
    }

    public function addOrder($field, $direction = \null)
    {
        $select = $this->getSelect();

        if ($field instanceof Expr) {
            $select->order($field);
            return $this;
        }

        if ($direction === \null || !in_array(strtolower($direction), ['asc', 'desc'])) {
            $direction = 'asc';
        }

        $table = $this->getTableByColumn($field);

        if (!$table instanceof TableAbstract) {
            \trigger_error(\sprintf('addOrder: Field %s not found in model %s tables', $field, \get_class($this)), \E_USER_NOTICE);
            return $this;
        }

        $alias = $table->info(TableAbstract::NAME);

        $select->order("{$alias}.{$field} {$direction}");

        return $this;
    }

    public function setOrder(array $order)
    {
        $this->getSelect()->reset('order');

        foreach ($order as $key => $value) {
            if (\is_numeric($key)) {
                $this->addOrder($value);
            } else {
                $this->addOrder($key, $value);
            }
        }

        return $this;
    }

    public function resetSelect()
    {
        $this->select = \null;

        return $this;
    }

    public function resetOrder()
    {
        $this->getSelect()->reset('order');

        return $this;
    }

    public function resetWhere()
    {
        $this->getSelect()->reset('where');

        return $this;
    }

    public function addJoinCondition($field, $value)
    {
        $table = $this->getTableByColumn($field, \true);

        if (!$table instanceof TableAbstract) {
            \trigger_error(\sprintf('addJoinCondition: Field %s not found in model %s dependent tables', $field, \get_class($this)), \E_USER_NOTICE);
            return $this;
        }

        $this->getSelect()->joinWhere(
            $table->info(TableAbstract::NAME),
            $field,
            $value
        );

        return $this;
    }

    public function getSelect()
    {
        if (\null === $this->select) {
            $this->select = $this->buildSelect();
        }

        return $this->select;
    }

    public function setSelect(Select $select)
    {
        $this->select = $select;

        return $this;
    }

    public function addFilters(array $params)
    {
        foreach ($params as $key => $value) {

            if (($table = $this->getTableByColumn($key)) === \null) {
                continue;
            }

            if ($value instanceof Expr) {
                $this->addWhere($value);
            } else {
                $metadata = $table->info('metadata');
                if (\is_scalar($value) && isset($metadata[$key]) && \preg_match('~(?:char|text|blob)$~', $metadata[$key]['DATA_TYPE'])) {
                    $this->addWhere($key, "%{$value}%");
                } else {
                    $this->addWhere($key, $value);
                }
            }
        }

        return $this;
    }

    public function getItems($offset = \null, $itemCountPerPage = \null)
    {
        $this->trigger('Before.Load', ['model' => $this]);

        $select = $this->getSelect();

        $select->limit($itemCountPerPage, $offset);

        $result = $this->rowsetToObjects(
            $this->tableInstance->fetchAll($select)
        );

        return $result;
    }

    public function getItem()
    {
        $this->resetOrder();

        $items = $this->getItems(0, 1);

        if (!empty($items)) {
            return \current($items);
        }

        return \null;
    }

    public function buildSelect()
    {
        $select = $this->tableInstance
                       ->select($this->tableInstance)
                       ->setIntegrityCheck(\false);

        $tableInfo = $this->tableInstance->info();

        $columns = $tableInfo['cols'];

        foreach ($this->tableInstance->getDependentTables() as $dependentTable) {

            $dependentTableInstance = $this->tableInstance->getDependentTableInstance($dependentTable);

            $dependentTableInfo = $dependentTableInstance->info();

            $columns = \array_merge($columns, $dependentTableInfo['cols']);

            $reference = $dependentTableInstance->getReference(get_class($this->tableInstance));

            if (isset($reference['read']) && !$reference['read']) {
                continue;
            }

            $onCondition = [];

            foreach ($reference['columns'] as $k => $column) {

                $refColumn = $reference['refColumns'][$k];

                $onCondition[] = $this->tableInstance->getAdapter()->quoteIdentifier($tableInfo['name']) . '.' . $this->tableInstance->getAdapter()->quoteIdentifier($refColumn) . ' = ' . $this->tableInstance->getAdapter()->quoteIdentifier($dependentTableInfo['name']) . '.' . $this->tableInstance->getAdapter()->quoteIdentifier($column);
            }

            if (empty($onCondition)) {
                throw new CoreException('Cannot join tables', 500);
            }

            $dependentTableAlias = $dependentTableInfo['name'];

            $joinType = 'joinLeft';

            if (isset($reference['joinType'])) {
                switch ($reference['joinType']) {
                    case 'inner' :
                        $joinType = 'joinInner';
                        break;
                }
            }

            $select->$joinType(
                $dependentTableInfo['name'],
                \implode(" AND ", $onCondition),
                \array_diff($dependentTableInfo['cols'], $tableInfo['cols'])
            );
        }

        return $select;
    }

    #[\ReturnTypeWillChange]
    public function count()
    {
        $this->trigger('Before.Count', ['model' => $this]);

        $select = $this->getSelect();

        $select = clone $select;

        $select->reset('columns');
        $select->reset('limitcount');
        $select->reset('limitoffset');
        $select->reset('order');

        $select->columns(new Expr('count(*) as cnt'));

        $result = $this->tableInstance->getAdapter()->fetchCol($select);

        if (\count($select->getPart('group')) > 0) {
            $count = \count($result);
        } else {
            $count = $result[0];
        }

        return $count;
    }

    public function save(EntityInterface $entity)
    {
        try {

            $this->beginTransaction();

            $this->trigger('Before.Save', ['model' => $this, 'entity' => $entity]);

            $data = $originalData = $entity->toArray();

            $data = \array_merge($data, $this->saveToTable($this->tableInstance, $data));

            foreach ($this->tableInstance->getDependentTables() as $dependentTable) {

                $dependentTableInstance = $this->tableInstance->getDependentTableInstance($dependentTable);

                $reference = $dependentTableInstance->getReference(\get_class($this->tableInstance));

                if (isset($reference['save']) && !$reference['save']) {
                    continue;
                }

                foreach ($data as $k => $v) {
                    $refColumnKey = \array_search($k, $reference['refColumns']);
                    if ($refColumnKey !== \false) {
                        $data[$reference['columns'][$refColumnKey]] = $v;
                    }
                }

                $data = \array_merge($data, $this->saveToTable($dependentTableInstance, $data, \true));
            }

            $entity->setFromArray($data);

            $this->trigger('After.Save', ['model' => $this, 'entity' => $entity, 'originalData' => $originalData]);

            $this->removeCache();

            $this->commit();

        } catch (\Throwable $e) {
            $this->rollback();
            $entity->setFromArray($originalData);
            throw $e;
        }

        return $entity;
    }

    public function saveToTable(TableAbstract $table, array $data, $dependency = \false)
    {
        /**
         * Force primary keys as array in case we use compound primary key
         */
        $primaryKeys = (array) $table->info(TableAbstract::PRIMARY);

        $primaryValues = [];

        $primaryValuesFilled = \false;

        foreach ($primaryKeys as $primaryKey) {

            if (!\array_key_exists($primaryKey, $data)) {
                $data[$primaryKey] = \null;
            }

            if (!empty($data[$primaryKey])) {
                $primaryValuesFilled = \true;
            }

            $primaryValues[] = $data[$primaryKey];
        }

        $rowSet = \null;

        /**
         * Try to find row from primary table based on primary key(s) value(s)
         */
        if ($primaryValuesFilled) {
            $rowSet = \call_user_func_array([$table, 'find'], $primaryValues);
        }

        /**
         * Get current row from rowset or fetch new one if we'll insert
         */
        if ($rowSet && $rowSet->count()) {
            $row = $rowSet->current();
        } else {
            $row = $table->createRow();
        }

        try {
            foreach ($data as $column => $value) {
                if ($value instanceof \DateTime) {
                    $data[$column] = $value->format('Y-m-d H:i:s');
                }
            }
            $row->setFromArray($data);
            $row->save();
        } catch (\Throwable $e) {
            throw $e;
        }

        /**
         * Merge type data with possible new info from database
         */
        $data = \array_merge($data, $row->toArray());

        return $data;
    }

    /**
     * Primary key
     * @return string
     */
    public function getIdentifier()
    {
        return \current($this->getTable()->info('primary'));
    }

    /**
     * @param EntityInterface $entity
     * @return int
     */
    public function delete(EntityInterface $entity)
    {
        $affected = 0;

        try {

            $this->beginTransaction();

            $this->trigger('Before.Delete', ['model' => $this, 'entity' => $entity]);

            $affected = $this->getTable()->delete(['id = ?' => $entity[$this->getIdentifier()]]);

            $this->trigger('After.Delete', ['model' => $this, 'entity' => $entity, 'affected' => $affected]);

            $this->removeCache();

            $this->commit();

        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }

        return $affected;
    }

    /**
     * @param EntityInterface $entity
     * @param $active
     * @return int
     * @throws \Micro\Database\Table\Exception
     * @internal param $ids
     */
    public function activate(EntityInterface $entity, $active)
    {
        $affected = 0;

        $cols = $this->getTable()->info('cols');

        if (!\in_array('active', $cols)) {
            return $affected;
        }

        try {

            $this->beginTransaction();

            $this->trigger('Before.Activate', ['model' => $this, 'entity' => $entity, 'active' => $active]);

            $affected = $this->getTable()->update(['active' => ((int) $active ? 1 : 0)], [$this->getIdentifier() . ' = ?' => $entity[$this->getIdentifier()]]);

            $this->trigger('After.Activate', ['model' => $this, 'entity' => $entity, 'active' => $active]);

            $this->removeCache();

            $this->commit();

        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }

        return $affected;
    }

    final protected function rowToObject($row)
    {
        if (!\is_array($row) && !$row instanceof RowAbstract) {
            return \null;
        }

        $entity = $this->createEntity();

        $entity->setFromArray(
            \is_array($row)
            ? $row
            : $row->toArray()
        );

        $this->trigger('Load', ['model' => $this, 'entity' => $entity]);

        return $entity;
    }

    final protected function rowsetToObjects($rowset)
    {
        $results = [];

        if (!\is_array($rowset) && !$rowset instanceof RowsetAbstract) {
            return [];
        }

        if (count($rowset) === 0) {
            return [];
        }

        $primary = $this->tableInstance->info('primary');
        $primary = \current($primary);

        foreach ($rowset as $row) {
            $results[$row->{$primary}] = $this->rowToObject($row);
        }

        return $results;
    }

    /**
     * @param array $where
     * @param array $fields
     * @param array $order
     * @param boolean $cacheIt
     */
    public function fetchPairs(?array $where = \null, ?array $fields = \null, ?array $order = \null, $cacheIt = \false)
    {
        $this->resetSelect();

        $adapter = $this->getTable()->getAdapter();
        $cache = \null;

        if ($cacheIt !== \false) {
            $cache = $this->cache;
        }

        $select = $this->makeFetchPairsSelect($where, $fields, $order);

        $cacheId = $this->getCacheId() . '_' . \md5($select->__toString());

        if ($cache === \null || ($pairs = $cache->load($cacheId)) === \false) {

            try {
                $pairs = $adapter->fetchPairs($select);
            } catch (\Throwable $e) {
                \trigger_error($e->getMessage(), \E_USER_NOTICE);
                $pairs = [];
            }

            if ($cache !== \null) {
                $cache->save($pairs, $cacheId, [$this->getCacheId(), 'Model']);
            }
        }

        return $pairs;
    }

    /**
     * @param array $where
     * @param array $fields
     * @param array $order
     */
    public function fetchCachedPairs(?array $where = \null, ?array $fields = \null, ?array $order = \null)
    {
        return $this->fetchPairs($where, $fields, $order, \true);
    }

    /**
     * @param array $where
     * @param array $fields
     * @param array $order
     */
    protected function makeFetchPairsSelect(?array $where = \null, ?array $fields = \null, ?array $order = \null)
    {
        if ($where !== \null) {
            foreach ($where as $k => $v) {
                if ($v instanceof Expr) {
                    $this->addWhere($v);
                } else {
                    $this->addWhere($k, $v);
                }
            }
        }

        if ($order !== \null) {
            foreach ($order as $k => $v) {
                $this->addOrder($k, $v);
            }
        } else {
            $this->addOrder('id', 'DESC');
        }

        if ($fields !== \null) {
            $key   = $fields[0];
            $value = $fields[1];
        } else {
            $key   = $this->getIdentifier();
            $value = $this->getIdentifier();
            $table = $this->getTableByColumn('name');
            if ($table) {
                $value = $table->info('name') . '.name';
            }
        }

        $select = $this->getSelect();

        $select->reset('columns')->columns([$key, $value]);

        return $select;
    }

    public function beginTransaction()
    {
        if (self::$transactionLevel === 0) {
            $db = $this->tableInstance->getAdapter();
            if ($db) {
                $db->beginTransaction();
            }
        }

        self::$transactionLevel++;

        return $this;
    }

    public function commit()
    {
        if (self::$transactionLevel === 1) {
            $db = $this->tableInstance->getAdapter();
            if ($db) {
                $db->commit();
            }
        }

        self::$transactionLevel--;

        return $this;
    }

    public function rollback()
    {
        if (self::$transactionLevel === 1) {
            $db = $this->tableInstance->getAdapter();
            if ($db) {
                $db->rollBack();
            }
        }

        self::$transactionLevel--;

        return $this;
    }

    public function removeCache()
    {
        foreach (Cache\Cache::loadAll() as $cache) {
            $cache->clean(Cache\Cache::CLEANING_MODE_MATCHING_TAG, [$this->getCacheId()]);
        }

        return $this;
    }

    public function getCacheId()
    {
        return \preg_replace('~[^a-zA-Z0-9_]~', '_', \get_class($this));
    }

    public function trigger($event, ?array $params = \null)
    {
        if ($this->eventManager === \null) {
            return $this;
        }

        $event = \str_replace('\\', '.', \get_class($this)) . '.' . $event;

        $this->eventManager->trigger($event, $params);

        return $this;
    }

    public static function __callstatic($name, $args)
    {
        $called = \get_called_class();

        if (($pos = \strpos($name, 'call')) !== 0) {
            throw new CoreException(__METHOD__, 500);
        }

        return \call_user_func_array([new $called, \substr($name, 4)], $args);
    }

    /**
     * @return the $enableLog
     */
    public function getEnableLog()
    {
        return $this->enableLog;
    }

    /**
     * @param boolean $enableLog
     */
    public function setEnableLog($enableLog)
    {
        $this->enableLog = (bool) $enableLog;
    }

    /**
     * Safely quotes a value for an SQL statement.
     *
     * If an array is passed as the value, the array values are quoted
     * and then returned as a comma-separated string.
     *
     * @param mixed $value The value to quote.
     * @param mixed $type  OPTIONAL the SQL datatype name, or constant, or null.
     * @return mixed An SQL-safe quoted value (or string of separated values).
     */
    public function quote($value, $type = null)
    {
        return $this->getTable()->getAdapter()->quote($value, $type);
    }
}