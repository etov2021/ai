<?php

namespace Pages\Controller\Admin;

use Categories\Controller\Admin\Categories as CategoriesBase;
use Pages\Model\Entity\Page as EntityPage;

class Categories extends CategoriesBase
{
    protected $target = EntityPage::class;
}