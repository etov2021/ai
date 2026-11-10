<?php

namespace Translations\Model\Table;

use Micro\Database\Table\TableAbstract;

class TranslationsLang extends TableAbstract
{
    protected $_name = 'TranslationsLang';

    protected $_referenceMap = [
        'Translation' => [
            'columns'       => 'translationId',
            'refTableClass' => Translations::class,
            'refColumns'    => 'id',
            'save'          => false,
        ]
    ];

    public function getLanguagePairs($translationId)
    {
        $select = $this->select(true)
                       ->where('translationId = ?', (int) $translationId)
                       ->reset('columns')
                       ->columns(['languageId', 'name']);

        return $this->getAdapter()->fetchPairs($select);
    }
}