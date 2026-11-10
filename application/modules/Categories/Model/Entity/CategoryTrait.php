<?php

namespace Categories\Model\Entity;

use Categories\Model\Categories as ModelCategories;

trait CategoryTrait
{
    protected $categoryId;
    protected $category;

    public function getCategoryId()
    {
        return $this->categoryId;
    }

    public function setCategoryId($categoryId)
    {
        $this->categoryId = $categoryId;
    }

    public function getCategory()
    {
        $ids = $this->getCategoryId();

        if ($this->category === \null && !empty($ids)) {

            $settings = $this->getCategorySettings() + ['isArray' => 0];

            $model = new ModelCategories();
            $model->setSettings($settings);
            $model->useUiLanguage();
            $model->addWhere('id', $ids);

            if ($settings['isArray']) {
                $this->category = $model->getItems();
            } else {
                $this->category = $model->getItem();
            }
        }

        return $this->category;
    }
}