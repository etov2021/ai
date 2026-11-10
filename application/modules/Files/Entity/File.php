<?php

namespace Files\Entity;

use Micro\Model\Entity\EntityAbstract;
use Files\Table\Files;
use Micro\Container\SharedContainer;
use Translations\Model\Entity\Language;

class File extends EntityAbstract
{
    use CDNTrait;


    protected $id;
    protected $type;
    protected $lang;
    protected $target;
    protected $targetId;
    protected $hash;
    protected $extension;
    protected $blob;
    protected $file;
    protected $name;
    protected $mime;
    protected $size;
    protected $metadata;
    protected $tags;
    protected $customName;


    /**
     * Get file url
     * @return string
     */
    public function getUrl()
    {
        if ($this->getCdn()) {
            return \sprintf("%s/%s", $this->getCdn(), Files::getDir() . '/' . $this->target . '/' . $this->file);
        } else {
            return \base_url(Files::getDir() . '/' . $this->target . '/' . $this->file);
        }
    }

    /**
     * @return string
     */
    public function getPath()
    {
        if ($this->getCdn()) {
            return $this->getUrl();
        }

        return \public_path(Files::getDir() . DIRECTORY_SEPARATOR . $this->target . DIRECTORY_SEPARATOR . $this->file);
    }

    public function getFile()
    {
        return $this->file;
    }

    public function getMime()
    {
        return $this->mime;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getCustomName()
    {
        return $this->customName;
    }

    /**
     * @param string|null $path
     * @return boolean
     */
    public function isExists($path = null)
    {
        if ($this->getCdn()) {
            return true;
        } else {
            $path = $path ?: $this->getPath();
            return \is_file($path);
        }
    }

    /**
     * @param string|null $path
     * @return boolean
     */
    public function isImage($path = null)
    {
        if ($this->getCdn()) {
            return true;
        } else {
            $path = $path ?: $this->getPath();
            if ($this->isExists($path)) {
                $imagesize = @getimagesize($path);
                if ($imagesize && isset($imagesize['bits'])) {
                    return \true;
                }
            }
            return \false;
        }
    }

    /**
     * @return \true|\false
     */
    public function isLink()
    {
        if (!$this->getFile()) {
            return \true;
        }

        return \false;
    }

    /**
     * Get thumb url
     * @param string $type
     * @return string
     */
    public function getThumb($type)
    {
        if (empty($type)) {
            return \null;
        }

        $thumbPath = Files::getDir() . '/' . $this->target . '/thumbs/' . $type . '_' . $this->file;

        if($this->getCdn()) {
            return sprintf("%s/%s",$this->getCdn(), $thumbPath);
        } else {
            if ($this->isImage(\public_path($thumbPath)) === \false) {
                return \null;
            }
            return \base_url($thumbPath);
        }
    }

    public function setMetadata($metadata)
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function getMetadata()
    {

        return $this->metadata;
    }

    public function getMetadataField($field = null)
    {
        $lang = SharedContainer::getInstance()->get('language');
        $meta = $this->getMetadata();
        $name = null;

        if ($lang instanceof Language) {
            if (isset($meta[$lang->getCode()][$field])) {
                $name = $meta[$lang->getCode()][$field];
            }
        } elseif (isset($meta[$field])) {
            $name = $meta[$field];
        }

        return $name;
    }

    public function getHash()
    {
        return $this->hash;
    }

    public function getLang()
    {
        return $this->lang;
    }

    public function getTarget()
    {
        return $this->target;
    }


}