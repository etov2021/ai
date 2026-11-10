<?php

namespace Files\Entity;

use Micro\Container\SharedContainer;

trait CDNTrait
{
    protected $cdn = null;

    public function init()
    {
       if ($this->cdn === null) {
            $cdn = \config('cdn');
            if ($cdn && isset($cdn['host'])) {
                $this->cdn = $cdn['host'];
            } else {
                $this->cdn = false;
            }
            $currentRoute = SharedContainer::getInstance()->get('router')->getCurrentRoute();
            if($currentRoute) {
                $routeName = $currentRoute->getName();
                if ($routeName && \preg_match('~^admin~', $routeName)) {
                    $this->cdn = false;
                }
            }
        }
    }

    public function disableCdn()
    {
        $this->cdn = false;
    }

    public function getCdn()
    {
        $this->init();

        return $this->cdn;
    }
}