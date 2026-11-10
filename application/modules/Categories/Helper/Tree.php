<?php

namespace Categories\Helper;

use Micro\Database\Expr;
use Micro\Container\SharedContainer;
use Micro\Translator\Language\LanguageInterface;
use Micro\Helper\Tree\TreeInterface;
use Micro\Helper\Tree\TreeTrait;

class Tree implements TreeInterface
{
    use TreeTrait;

    protected $target;
    protected $domainId;

    public function __construct($target, $domainId = null)
    {
        if ($domainId === null) {
            try {
                $domain = SharedContainer::getInstance()->get('domain');
                if ($domain) {
                    $domainId = $domain->getId();
                }
            } catch (\Exception $e) {

            }
        }

        $this->target = $target;
        $this->domainId = (int) $domainId;
    }

    public function loadTree($active = 1)
    {
        $container = SharedContainer::getInstance();

        $db = $container->get('db');

        if (!$db) {
            return [];
        }

        $select = $db->select()
                     ->from('Categories')
                     ->where('categoryTarget = ?', $this->target)
                     ->columns([new Expr('1 as level')])
                     ->order('Categories.order ASC');

        if ($this->domainId) {
            $select->where('domainId = ?', $this->domainId);
        }

        if ($container->has('language') && ($language = $container->get('language')) instanceof LanguageInterface) {
            $select->joinLeft('CategoriesLang', 'CategoriesLang.categoryId = Categories.id AND CategoriesLang.languageId = ' . $language->getId(), ['name']);
        } else {
            $select->columns(['name' => 'id']);
        }

        if ($active !== \null) {
            $select->where('Categories.active = ?', ($active ? 1 : 0));
        }

        return $db->fetchAll($select);
    }
}