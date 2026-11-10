<?php

namespace Micro\Model;

use Micro\Model\Entity\EntityInterface;
use Micro\Database\Select;

interface ModelInterface extends \Countable
{
    /**
     * @return mixed
     */
    public function getIdentifier();

    /**
     * @return Select
     */
    public function getSelect();

    /**
     * @return ModelInterface
     */
    public function resetSelect();

    /**
     * @return ModelInterface
     */
    public function resetOrder();

    /**
     * @return ModelInterface
     */
    public function resetWhere();

    /**
     * @return EntityInterface
     */
    public function createEntity();

    /**
     * @param string $field
     * @param string $direction
     * @return ModelInterface
     */
    public function addOrder($field, $direction = \null);

    /**
     * @param array $order
     * @return ModelInterface
     */
    public function setOrder(array $order);

    /**
     * @param string $field
     * @param string $value
     * @return ModelInterface
     */
    public function addWhere($field, $value);

    /**
     * @param array $params
     * @return ModelInterface
     */
    public function addFilters(array $params);

    /**
     * @param int|array of primary keys
     * @return EntityInterface|null
     */
    public function find($id, $reset = \true);

    /**
     * @return EntityInterface
     */
    public function getItem();

    /**
     * @param int $offset
     * @param int $itemCountPerPage
     * @return array
     */
    public function getItems($offset = \null, $itemCountPerPage = \null);

    /**
     * @param array $where
     * @param array $fields
     * @param array $order
     * @param bool $cacheIt
     */
    public function fetchPairs(?array $where = \null, ?array $fields = \null, ?array $order = \null, $cacheIt = \false);

    /**
     * @param array $where
     * @param array $fields
     * @param array $order
     */
    public function fetchCachedPairs(?array $where = \null, ?array $fields = \null, ?array $order = \null);

    /**
     * @return ModelInterface
     */
    public function beginTransaction();

    /**
     * @return ModelInterface
     */
    public function commit();

    /**
     * @return ModelInterface
     */
    public function rollback();

    /**
     * @return ModelInterface
     */
    public function trigger($event, ?array $params = \null);

    /**
     * @param EntityInterface $entity
     * @return EntityInterface
     */
    public function save(EntityInterface $entity);

    /**
     * @param EntityInterface $entity
     */
    public function delete(EntityInterface $entity);

    /**
     * @param EntityInterface $entity
     * @param int $activate
     */
    public function activate(EntityInterface $entity, $activate);
}