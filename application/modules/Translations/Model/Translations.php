<?php

namespace Translations\Model;

use Micro\Model\TranslationDatabaseAbstract;
use Micro\Model\Entity\EntityInterface;
use Translations\Model\Table\TranslationsLang;
use Micro\Cache;
use Micro\Application\Utils;

class Translations extends TranslationDatabaseAbstract
{
    protected $entity = Entity\Translation::class;

    protected $table = Table\Translations::class;

    protected $cacheKey = 'json';

    public function removeCache()
    {
        $cache = \cache($this->cacheKey);

        if ($cache instanceof Cache\Core) {
            $cache->clean(Cache\Cache::CLEANING_MODE_MATCHING_TAG, array($this->getCacheId()));
        }
    }

    public function save(EntityInterface $entity)
    {
        $entity->setAlias(trim($entity->getAlias()));

        try {

            $this->beginTransaction();

            $result = parent::save($entity);

            $translationLangTable = new TranslationsLang();
            $translationLangTable->delete(['translationId = ?' => $entity->getId()]);

            $values = $entity->getValues();

            if (!empty($values)) {

                $values = Utils::arrayMapRecursive('trim', $values, true);

                foreach ($values as $language => $value) {

                    if (empty($value)) {
                        continue;
                    }

                    $row = $translationLangTable->fetchNew();
                    $row->translationId = $entity->getId();
                    $row->languageId = (int) $language;
                    $row->name = $value;
                    $row->save();
                }
            }

            $this->commit();

        } catch (\Exception $e) {
            $this->rollback();
            throw $e;
        }

        return $result;
    }

    public function getTranslations($code)
    {
        $currentDomainId = defined('DOMAIN') ? DOMAIN : 0;

        $cacheId = Cache\Cache::normalizeKey('Translations_' . $currentDomainId . '_' . $code);

        $cache = \cache($this->cacheKey);

        if (!$cache instanceof Cache\Core || ($translations = $cache->load($cacheId)) === false) {

            $select = $this->getSelect();
            $select->joinInner('Languages', 'Languages.id = TranslationsLang.languageId', []);
            $select->where('Languages.code = ?', $code);
            $select->where('Translations.active = 1');
            $select->reset('columns');
            $select->columns(['Translations.domainId', 'Translations.alias', 'TranslationsLang.name']);

            $rows = $this->getTable()->getAdapter()->fetchAll($select);

            $domains = [];

            foreach ($rows as $row) {

                $domainId = (int) $row['domainId'];

                if (!isset($domains[$domainId])) {
                    $domains[$domainId] = [];
                }

                $domains[$domainId][$row['alias']] = $row['name'];
            }

            $translations = [];

            if (!empty($domains)) {
                if ($currentDomainId !== 0 && isset($domains[$currentDomainId])) {
                    $translations = $domains[$currentDomainId];
                }
                $translations += $domains[0];
            }

            if ($cache instanceof Cache\Core) {
                $cache->save($translations, $cacheId, [$this->getCacheId()]);
            }
        }

        return $translations;
    }
}