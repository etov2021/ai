<?php

namespace Pages\Model;

use Micro\Model\TranslationDatabaseAbstract;
use Pages\Model\Entity\Page;
use Micro\Database\Expr;

class Pages extends TranslationDatabaseAbstract
{
    protected $entity = Entity\Page::class;

    protected $table = Table\Pages::class;

    public function getActivePage($id, $domainId = null)
    {
        $this->addWhere('id', $id);
        $this->addWhere('active', 1);
        $this->addWhere('domainId', ($domainId ?: (defined('DOMAIN') ? DOMAIN : 0)));
        $this->addWhere(new Expr("IFNULL(startDate,'2000-01-01') <= CURRENT_DATE()"));
        $this->addWhere(new Expr("IFNULL(endDate,'2199-01-01') >= CURRENT_DATE()"));
        $this->addWhere('type', 'page');

        return $this->getItem();
    }
}