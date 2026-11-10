<?php

namespace Micro\View\Helper;

use Micro\Container\SharedContainer;
use Micro\Router\Route;
use Micro\Http\Request;

class Placeholder extends HelperAbstract
{
    protected static $placeholders;

    public function __invoke($placeholder)
    {
        if (!isset(static::$placeholders)) {
            static::$placeholders = SharedContainer::getInstance()->get('config')
                                                                  ->get('placeholders', []);
        }

        $placeholders = isset(static::$placeholders[$placeholder])
                        ? static::$placeholders[$placeholder]
                        : [];

        if (empty($placeholders)) {
            return '';
        }

        $placeholders = \array_reverse($placeholders);

        \uasort($placeholders, function ($a, $b) {
            $aOrder = isset($a['order']) ? $a['order'] : 0;
            $bOrder = isset($b['order']) ? $b['order'] : 0;
            return $aOrder > $bOrder ? 1 : -1;
        });

        $content = '';

        foreach ($placeholders as $placeholder) {

            if (!isset($placeholder['name'])) {
                continue;
            }

            try {
                if (!$this->checkScope($placeholder)) {
                    continue;
                }
            } catch (\Throwable $e) {
                continue;
            }

            $name = $placeholder['name'];
            $params = [];

            if (isset($placeholder['params'])) {
                $params = \array_values((array) $placeholder['params']);
            }

            try {
                if (isset($placeholder['helper'])) {
                    $this->view->helper($placeholder['helper']);
                }
                $content .= (string) $this->view->__call($name, $params);
            } catch (\Throwable $e) {
                if (is_debug()) {
                    $content .= $e->__toString();
                }
            }
        }

        return $content;
    }

    protected function checkScope($placeholder)
    {
        if (isset($placeholder['scope']) && !empty($placeholder['scope'])) {
            $request = SharedContainer::getInstance()->get('request');
            if (!$request instanceof Request) {
                return \true;
            }
            $module     = $request->getAttribute('module');
            $controller = $request->getAttribute('controller');
            $action     = $request->getAttribute('action');
            $route      = $request->getAttribute(Route::class);
            foreach ($placeholder['scope'] as $scope) {
                if ($scope[0] !== '*' && $scope[0] !== $module) {
                    continue;
                }
                if ($scope[1] !== '*' && $scope[1] !== $controller) {
                    continue;
                }
                if ($scope[2] !== '*' && $scope[2] !== $action) {
                    continue;
                }
                if (isset($scope[3]) && $scope[3] !== '*' && $scope[3] !== $route->getName())  {
                    continue;
                }
                return \true;
            }
            return \false;
        }

        return \true;
    }
}