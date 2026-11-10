<?php

namespace Categories\Model\Entity;

interface CategoryInterface
{
    public function getCategoryId();
    public function setCategoryId($categoryId);
    public function getCategorySettings();
    public function getCategory();
}