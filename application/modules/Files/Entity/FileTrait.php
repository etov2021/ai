<?php

namespace Files\Entity;

use Files\Table\Files as TableFiles;
use Micro\Model\Entity\TranslationInterface;

trait FileTrait
{
    protected $__files = [];
    protected $__filesCached = [];

    public function getFiles($type)
    {
        if (!isset($this->__files[$type])) {
            $this->__files[$type] = (new TableFiles())->getFiles($this->getTarget(), (int) $this->getTargetId(), $type, $this->__getFileLang($type));
        }

        return $this->__files[$type];
    }

    public function getFilesCache($type)
    {
        if (!isset($this->__filesCached[$type])) {
            $this->__filesCached[$type] = (new TableFiles())->getFilesForTargetId($this->getTarget(), (int) $this->getTargetId(), $type, true, $this->__getFileLang($type));
        }

        return $this->__filesCached[$type];
    }

    protected function __getFileLang($type)
    {
        $lang = \null;

        if ($this instanceof FileInterface) {
            $fileSettings = $this->getFileSettings();
            if (isset($fileSettings[$type]) && isset($fileSettings[$type]['language_dependent']) && $fileSettings[$type]['language_dependent']) {
                if ($this instanceof TranslationInterface) {
                    $lang = (int) $this->getLanguageId();
                }
            }
        }

        return $lang;
    }
}