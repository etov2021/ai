<?php

namespace Micro\Model;

use Micro\Translator\Language\LanguageInterface;
use Micro\Model\Entity\EntityInterface;
use Micro\Model\Entity\TranslationInterface as EntityTranslationInterface;
use Micro\Exception\Exception as CoreException;
use Micro\Container\SharedContainer;
use Micro\Database\Expr;
use Micro\Cache;
use Micro\Event;
use Micro\Database\Table\TableAbstract;

abstract class TranslationDatabaseAbstract extends DatabaseAbstract implements TranslationInterface
{
    /**
     * @var LanguageInterface
     */
    protected $language;

    protected $returnDefaultTranslation = \false;

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

        parent::__construct($table, $entity, $eventManager, $cache);

        $this->useUiLanguage();
    }

    /**
     * @throws CoreException
     * @return LanguageInterface
     */
    public function getLanguage()
    {
        if ($this->language === \null) {
            throw new CoreException(\sprintf('Language is not set in the translatable model %s. Use useCurrentLanguage() оr useUiLanguage() on model', \get_class($this)));
        }

        return $this->language;
    }

    /**
     * @return boolean
     */
    public function hasLanguage()
    {
        return $this->language !== \null;
    }

    /**
     * @param LanguageInterface $language
     */
    public function setLanguage(LanguageInterface $language)
    {
        $this->language = $language;

        return $this;
    }

    public function setReturnDefaultTranslation($flag)
    {
        $this->returnDefaultTranslation = (bool) $flag;

        return $this;
    }

    public function getItems($offset = \null, $itemCountPerPage = \null)
    {
        $this->addJoinCondition('languageId', $this->getLanguage()->getId());

        if ($this->returnDefaultTranslation) {
            $this->joinWithDefaultTranslation();
        }

        return parent::getItems($offset, $itemCountPerPage);
    }

    public function count()
    {
        $this->addJoinCondition('languageId', $this->getLanguage()->getId());

        return parent::count();
    }

    public function save(EntityInterface $entity)
    {
        // usual in add action
        if ($entity instanceof EntityTranslationInterface && !$entity->getLanguageId()) {
            $entity->setLanguageId($this->getLanguage()->getId());
    		$entity->addTranslation($this->getLanguage());
        }

        return parent::save($entity);
    }

    public function deleteTranslation(EntityTranslationInterface $entity, LanguageInterface $language)
    {
        $table = $this->getTableByColumn('languageId');

        if ($table) {

            $reference = $table->getReference(get_class($this->getTable()));

            if (isset($reference['refColumns'])) {

                $key = array_search($this->getIdentifier(), $reference['refColumns']);

                if ($key !== false && isset($reference['columns'][$key])) {

                    $adapter = $this->getTable()->getAdapter();

                    try {

                        $adapter->beginTransaction();

                        $entity->removeTranslation($language);

                        $this->getTable()->update(['translations' => $entity->getTranslations()], [$this->getIdentifier() . ' = ?' => $entity[$this->getIdentifier()]]);

                        $table->delete(['languageId = ?' => $language->getId(), $reference['columns'][$key] . ' = ?' => $entity[$this->getIdentifier()]]);

                        $adapter->commit();

                    } catch (\Throwable $e) {
                        $adapter->rollBack();
                        throw $e;
                    }
                }
            }
        }
    }

    public function find($id, $reset = \true)
    {
        if ($reset !== \false) {
            $this->resetSelect();
        }

        $this->addJoinCondition('languageId', $this->getLanguage()->getId());

        if ($this->returnDefaultTranslation) {
            $this->joinWithDefaultTranslation();
        }

        return parent::find($id, \false);
    }

    protected function makeFetchPairsSelect(?array $where = \null, ?array $fields = \null, ?array $order = \null)
    {
        $this->addJoinCondition('languageId', $this->getLanguage()->getId());

        if ($this->returnDefaultTranslation) {
            $this->joinWithDefaultTranslation();
        }

        return parent::makeFetchPairsSelect($where, $fields, $order);
    }

    public function useDefaultLanguage()
    {
        $sharedContainer = SharedContainer::getInstance();

        if ($sharedContainer->has('default_language')) {
            $this->setLanguage($sharedContainer->get('default_language'));
        }/*  else {
            throw new CoreException('Default Language service is not found. Translations module is not loaded.');
        } */

        return $this;
    }

    public function useUiLanguage()
    {
        $sharedContainer = SharedContainer::getInstance();

        if ($sharedContainer->has('language')) {
            $this->setLanguage($sharedContainer->get('language'));
        }/*  else {
            throw new CoreException('Language service is not found. Translations module is not loaded.');
        } */

        return $this;
    }

    public function useCurrentLanguage()
    {
        $sharedContainer = SharedContainer::getInstance();

        if ($sharedContainer->has('current_language')) {
            $this->setLanguage($sharedContainer->get('current_language'));
        }/*  else {
            throw new CoreException('Current Language service is not found. Translations module is not loaded.');
        } */

        return $this;
    }

    public function useLanguageById($languageId, $useCurrentOnFail = false)
    {
        $sharedContainer = SharedContainer::getInstance();

        if (!$sharedContainer->has('languages')) {
            throw new CoreException('Languages service is not found. Translations module is not loaded.');
        }

        $found = false;
        $languages = $sharedContainer->get('languages');
        foreach ($languages as $language) {
            if ($language->getId() == $languageId) {
                $found = true;
                $this->setLanguage($language);
                break;
            }
        }

        if (!$found && $useCurrentOnFail) {
            $this->useCurrentLanguage();
        }

        return $this;
    }

    public function useLanguageByCode($code, $useCurrentOnFail = false)
    {
        $sharedContainer = SharedContainer::getInstance();

        if (!$sharedContainer->has('languages')) {
            throw new CoreException('Languages service is not found. Translations module is not loaded.');
        }

        $languages = $sharedContainer->get('languages');

        $code = strtolower($code);

        if (!empty($languages) && isset($languages[$code])) {
            $this->setLanguage($languages[$code]);
        } elseif ($useCurrentOnFail) {
            $this->useCurrentLanguage();
        }

        return $this;
    }

    protected function joinWithDefaultTranslation()
    {
        $sharedContainer = SharedContainer::getInstance();

        if (!$sharedContainer->has('default_language')) {
            throw new CoreException('Default Language service is not found. Translations module is not loaded.');
        }

        $defaultLanguage = $sharedContainer->get('default_language');

        if ($this->getLanguage()->getId() != $defaultLanguage->getId()) {

            $table = $this->getTable();

            $qi = function ($ident) use ($table) {
                return $table->getAdapter()->quoteIdentifier($ident);
            };

            foreach ($table->getDependentTables() as $dt) {

                if (\preg_match('~Lang$~', $dt)) {

                    $dependentTableInstance = $table->getDependentTableInstance($dt);

                    $reference = $dependentTableInstance->getReference(\get_class($table));

                    if (isset($reference['read']) && !$reference['read']) {
                        break;
                    }

                    $info = $table->info();

                    $dependentInfo = $dependentTableInstance->info();

                    $cols = \array_diff($dependentInfo['cols'], $info['cols']);

                    $aliases = [];

                    foreach ($cols as $col) {
                        if (\in_array($col, $dependentInfo['primary'])) {
                            continue;
                        }
                        $aliases[$col] = new Expr('IFNULL(' . $qi($dependentInfo['name']) . '.' . $qi($col) . ', ' . $qi('t') . '.' . $qi($col) . ')');
                    }

                    $joinType = 'joinLeft';

                    if (isset($reference['joinType'])) {
                        switch ($reference['joinType']) {
                            case 'inner' :
                                $joinType = 'joinInner';
                                break;
                        }
                    }

                    if (!empty($aliases) && $joinType == 'joinLeft') {
                        $this->getSelect()->$joinType(['t' => $dependentInfo['name']], $qi('t') . '.' . $qi(\current($dependentInfo['primary'])) . ' = ' . $qi($info['name']) . '.' . $qi($this->getIdentifier()) . ' AND ' . $qi('t') . '.' . $qi('languageId') . ' = ' . $defaultLanguage->getId(), $aliases);
                    }

                    break;
                }
            }
        }
    }

    /**
     * {@inheritDoc}
     * @see \Micro\Model\DatabaseAbstract::activate()
     */
    public function activate(EntityInterface $entity, $active)
    {
        $affected = 0;

        $cols = $this->getTable()->info('cols');

        if (\in_array('active', $cols)) {
            return parent::activate($entity, $active);
        }

        $table = $this->getTableByColumn('active');
        $reference = $table->getReference(get_class($this->getTable()));

        $where = [];

        foreach ($table->info('primary') as $primary) {
            $primaryValue = 0;
            if (isset($entity[$primary])) {
                $primaryValue = $entity[$primary];
            } else if (isset($reference['columns'])) {
                $key = array_search($primary, $reference['columns']);
                if ($key !== false && isset($reference['refColumns'][$key])) {
                    $primaryValue = $entity[$reference['refColumns'][$key]];
                }
            }
            $where[$primary . ' = ?'] = $primaryValue;
        }

        try {

            $this->beginTransaction();

            $this->trigger('Before.Activate', ['model' => $this, 'entity' => $entity, 'active' => $active]);

            if (!empty($where)) {
                $affected = $table->update(['active' => ((int) $active ? 1 : 0)], $where);
            }

            $this->trigger('After.Activate', ['model' => $this, 'entity' => $entity, 'active' => $active]);

            $this->removeCache();

            $this->commit();

        } catch (\Throwable $e) {

            $this->rollback();

            throw $e;
        }

        return $affected;
    }
}