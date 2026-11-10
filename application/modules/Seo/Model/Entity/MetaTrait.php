<?php

namespace Seo\Model\Entity;

use Seo\Model\Meta as ModelMeta;

trait MetaTrait
{
    protected $__metaTitle;
    protected $__metaDescription;
    protected $__meta;

    /**
     * @return the $metaTitle
     */
    public function getMetaTitle()
    {
        if ($this->__metaTitle === \null) {
            $meta = $this->__loadMeta();
            if (!empty($meta)) {
                $this->__metaTitle = (string) $meta['title'];
            } else {
                $this->__metaTitle = '';
            }
        }

        return $this->__metaTitle;
    }

    /**
     * @return the $metaDescription
     */
    public function getMetaDescription()
    {
        if ($this->__metaDescription === \null) {
            $meta = $this->__loadMeta();
            if (!empty($meta)) {
                $this->__metaDescription = (string) $meta['description'];
            } else {
                $this->__metaDescription = '';
            }
        }

        return $this->__metaDescription;
    }

    public function __loadMeta()
    {
        if ($this->__meta === \null) {
            $this->__meta = (new ModelMeta())->loadMetaData($this->getTarget(), $this->getTargetId());
        }

        return $this->__meta;
    }
}