<?php

namespace Pages\Model\Entity;

use Micro\Model\Entity\EntityAbstract;
use Micro\Model\Entity\TranslationInterface;
use Micro\Model\Entity\TranslationTrait;
use Categories\Model\Entity\CategoryInterface;
use Categories\Model\Entity\CategoryTrait;
use Seo\Model\Entity\MetaInterface;
use Files\Entity\FileInterface;
use Seo\Model\Entity\MetaTrait;
use Files\Entity\FileTrait;
use Micro\Model\Entity\TargetTrait;
use Domains\Model\Entity\DomainInterface;
use Domains\Model\Entity\DomainTrait;

class Page extends EntityAbstract implements TranslationInterface, CategoryInterface, MetaInterface, FileInterface, DomainInterface
{
    use TranslationTrait, CategoryTrait, MetaTrait, FileTrait, TargetTrait, DomainTrait;

    protected $id;
    protected $alias;
    protected $type;
    protected $name;
    protected $link;
    protected $content;
    protected $contentIsEditor = 1;
    protected $contentMobile;
    protected $contentMobileIsEditor = 1;
    protected $active = 1;

    protected $image;
    protected $file;

    protected $startDate;
    protected $endDate;
    protected $layout;
    protected $css;
    protected $viewScript;

    public static $TYPES = ['page' => 'Page', 'portlet' => 'Portlet'];

    public function getFileSettings()
    {
        $settings = [
            'image' => [
                'label' => 'Снимка',
                'validators' => [
                    'extension' => [
                        'validator' => 'File\Extension',
                        'options'   => [
                            'accept' => ['image/gif', 'image/png', 'image/bmp', 'image/jpeg'],
                        ],
                    ],
                    'size' => [
                        'validator' => 'File\Size',
                        'options' => [
                            'size' => 1,
                        ],
                    ],
                ],
            ],
            'file' => [
                'label' => 'Качи съдържанието като файл',
                'language_dependent' => 1,
                'attributes' => [
                    'data-lang-icon',
                ],
            ],
        ];

        return $settings;
    }

    public function getCategorySettings()
    {
        return [
            'target' => 'pages',
            'required' => 0,
            'parent' => 1,
            'leaf' => 1,
            'isArray' => 0,
        ];
    }

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = $id;
    }

    public function getAlias()
    {
        return $this->alias;
    }

    public function setAlias($alias)
    {
        $this->alias = $alias;
    }

    public function getLink()
    {
        return $this->link;
    }

    public function setLink($link)
    {
        $this->link = $link;
    }

    public function getContent()
    {
        return $this->content;
    }

    public function setContent ($content)
    {
        $this->content = $content;
    }

    public function getContentMobile ()
    {
        return $this->contentMobile;
    }

    public function setContentMobile ($contentMobile)
    {
        $this->contentMobile = $contentMobile;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    public function getActive()
    {
        return $this->active;
    }

    public function setActive($active)
    {
        $this->active = $active ? 1 : 0;
    }

    public function getStartDate()
    {
        return $this->startDate;
    }

    public function setStartDate($value)
    {
        $this->startDate = $value;
    }

    public function getEndDate()
    {
        return $this->endDate;
    }

    public function setEndDate($value)
    {
        $this->endDate = $value;
    }

    public function getUrl()
    {
       return route_reset('pages-detail', ['alias' => $this->getAlias(), 'page' => $this->getId()]);
    }

    public function getType()
    {
        return $this->type;
    }

    public function setType($type)
    {
        if (empty($type) || !isset(static::$TYPES[$type])) {
            $type = 'page';
        }

        $this->type = $type;
    }

    /**
     * @return the $layout
     */
    public function getLayout ()
    {
        return $this->layout;
    }

    /**
     * @param field_type $layout
     */
    public function setLayout ($layout)
    {
        $this->layout = $layout;
    }

    /**
     * @return the $css
     */
    public function getCss ()
    {
        return $this->css;
    }

    /**
     * @param field_type $css
     */
    public function setCss ($css)
    {
        $this->css = $css;
    }

    /**
     * @return the $viewScript
     */
    public function getViewScript ()
    {
        return $this->viewScript;
    }

    /**
     * @param field_type $viewScript
     */
    public function setViewScript ($viewScript)
    {
        $this->viewScript = $viewScript;
    }
    /**
     * @return the $contentIsEditor
     */
    public function getContentIsEditor()
    {
        return $this->contentIsEditor;
    }

    /**
     * @param number $contentIsEditor
     */
    public function setContentIsEditor($contentIsEditor)
    {
        $this->contentIsEditor = $contentIsEditor;
    }

    /**
     * @return the $contentMobileIsEditor
     */
    public function getContentMobileIsEditor()
    {
        return $this->contentMobileIsEditor;
    }

    /**
     * @param number $contentMobileIsEditor
     */
    public function setContentMobileIsEditor($contentMobileIsEditor)
    {
        $this->contentMobileIsEditor = $contentMobileIsEditor;
    }
}