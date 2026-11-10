<?php

namespace Seo\Model;

use Micro\Model\TranslationDatabaseAbstract;
use Micro\Model\ModelInterface;
use Micro\Model\Entity\EntityInterface;

class Meta extends TranslationDatabaseAbstract
{
    protected $entity = Entity\Meta::class;

    protected $table = Table\Meta::class;

    /**
     * @param ModelInterface $model
     * @param string $target
     * @param int $targetId
     * @return EntityInterface
     */
    public function getAlwaysMetaItem($target, $targetId)
    {
        $this->addWhere('target', $target);
        $this->addWhere('targetId', $targetId);

        $item = $this->getItem();

        if ($item === \null) {
            $item = $this->createEntity([
                'target'   => $target,
                'targetId' => $targetId,
            ]);
        }

        return $item;
    }

    public function loadMetaData($target, $targetId = null)
    {
        $languageId = $this->getLanguage()->getId();

        $callback = function () use ($target, $languageId) {
            $this->resetSelect();
            $this->addWhere('target', $target);
            $this->addWhere('languageId', $languageId);
            $data = [];
            foreach($this->getItems() as $item) {
                $data[$item['targetId']] = ['title' => $item['title'], 'description' => $item['description']];
            }
            return $data;
        };

        $data = \cache_data('json', 'Meta_' . $target . '_' . $languageId, $callback, ['Meta_' . $target]);

        if ($targetId !== null) {
            return isset($data[$targetId]) ? $data[$targetId] : null;
        }

        return $data;
    }
}