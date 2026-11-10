<?php

namespace Email\Model;

use Micro\Model\TranslationDatabaseAbstract;
use Micro\Translator\Language\LanguageInterface;

class Templates extends TranslationDatabaseAbstract
{
    protected $entity = Entity\Template::class;

    protected $table = Table\Templates::class;

    public function getTemplate($alias, ?LanguageInterface $language = null, $domainId = null)
    {
        $this->resetSelect(true);

        if ($language !== null) {
            $this->setLanguage($language);

        } else {
            $this->useUiLanguage();
        }

        $this->addWhere('languageId',$this->getLanguage()->getId());

        if (is_numeric($alias)) {
            $this->addWhere('id', $alias);
        } else {
            $this->addWhere('alias', $alias);
        }

        if ($domainId !== null) {
            $this->addWhere('domainId', $domainId);
        }



        return $this->getItem();
    }
}