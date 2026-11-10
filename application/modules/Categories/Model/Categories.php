<?php

namespace Categories\Model;

use Micro\Model\TranslationDatabaseAbstract;
use Micro\Model\Entity\EntityInterface;

class Categories extends TranslationDatabaseAbstract
{
    protected $settings = [];

    protected $entity = Entity\Category::class;

    protected $table = Table\Categories::class;

    public function setSettings(array $settings)
    {
        $this->settings = $settings;

        return $this;
    }

    public function getSettings()
    {
        return $this->settings + ['target' => null, 'parent' => false, 'sortable' => false];
    }

    public function getItems($offset = \null, $itemCountPerPage = \null)
    {
        $settings = $this->getSettings();

        if ($settings['target'] !== \null) {
            $this->addWhere('categoryTarget', $settings['target']);
        }

        return parent::getItems($offset, $itemCountPerPage);
    }

    public function count()
    {
        $settings = $this->getSettings();

        if ($settings['target'] !== \null) {
            $this->addWhere('categoryTarget', $settings['target']);
        }

        return parent::count();
    }

    public function save(EntityInterface $entity)
    {
        $settings = $this->getSettings();

        if ($settings['target'] !== \null) {
            $entity->setCategoryTarget($settings['target']);
        }

        if (!$settings['parent']) {
            $entity->setParentId(\null);
        }

        $hasOrder = $entity->getOrder() !== \null;

        $result = parent::save($entity);

        if ($hasOrder === \false) {

            $domainId = $entity->getDomainId();

            if ($entity->getParentId()) {
                $and = ' AND parentId = ' . (int) $entity->getParentId();
            } else {
                $and = ' AND parentId IS NULL';
            }

            $sql = '
                SELECT
                IFNULL(MAX(`order`), 0) + 1
                FROM Categories
                WHERE categoryTarget = "' . $settings['target'] . '"'
                . ($domainId ? ' AND domainId = ' . $domainId : '')
                . $and
            ;

            $nextOrder = $this->getTable()->getAdapter()->fetchOne($sql);

            $this->getTable()->update(['order' => $nextOrder], ['id = ?' => $entity->getId()]);

            $entity->setOrder($nextOrder);
        }

        return $result;
    }

    public function find($id, $reset = \true)
    {
        if ($reset !== \false) {
            $this->resetSelect();
        }

        $settings = $this->getSettings();

        if ($settings['target'] !== \null) {
            $this->addWhere('categoryTarget', $settings['target']);
        }

        return parent::find($id, \false);
    }

    protected function makeFetchPairsSelect(?array $where = \null, ?array $fields = \null, ?array $order = \null)
    {
        $settings = $this->getSettings();

        if ($settings['target'] !== \null) {
            $this->addWhere('categoryTarget', $settings['target']);
        }

        return parent::makeFetchPairsSelect($where, $fields, $order);
    }

    public function getCacheId()
    {
        $settings = $this->getSettings();

        if ($settings['target'] !== \null) {
            return \preg_replace('~[^a-zA-Z0-9_]~', '_', \get_class($this) . '_' . ucfirst($settings['target']));
        }

        return parent::getCacheId();
    }
}