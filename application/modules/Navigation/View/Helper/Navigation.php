<?php

namespace Navigation\View\Helper;

use Micro\Navigation\Navigation as NavigationContainer;
use Micro\Navigation\Page\Page as NavigationPage;
use Navigation\Helper;
use Navigation\Model\Menu;
use Micro\View\Helper\HelperAbstract;
use Micro\Container\SharedContainer;
use Micro\Cache\Cache;
use Micro\Lock\Lock;

class Navigation extends HelperAbstract
{
    /**
     * @return array of NavigationContainer
     */
    protected static $menus = [];

    /**
     * @param string $alias
     * @param int $domainId
     */
    public function __invoke($alias, $domainId = null)
    {
        return static::getMenuByAlias($alias, $domainId);
    }

    /**
     * @param string $alias
     * @param int $domainId
     */
    protected static function getMenuByAlias($alias, $domainId = null)
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

        if (!isset(static::$menus[$alias])) {
            static::$menus[$alias] = static::getContainer($alias, (int) $domainId);
        }

        return static::$menus[$alias];
    }

    /**
     * @param int $domainId
     * @param \Closure $callback
     * @return array of NavigationContainer
     */
    public static function getMenus($domainId = null, ?\Closure $callback = null)
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

        $menusPairs = (new Menu())->fetchCachedPairs(['domainId' => (int) $domainId], ['alias', 'alias']);

        $menus = [];

        foreach ($menusPairs as $alias) {

            if ($callback !== null && !$callback($alias)) {
                continue;
            }

            $menus[$alias] = static::getMenuByAlias($alias, $domainId);
        }

        return $menus;
    }

    /**
     * @param string $alias
     * @param int $domainId
     * @return NavigationContainer
     */
    protected static function getContainer($alias, $domainId)
    {
        $canCache = \config('navigation.cache', \true);

        $cache = $canCache ? \app('cache', \true) : null;
        $language = \app('language', \true);

        $cacheId = 'Menu_' . $alias . '_' . ($domainId ?: '0') . '_' . ($language ? $language->getId() : 0);
        $cacheId = Cache::normalizeKey($cacheId);

        if (empty($cache) || ($container = $cache->load($cacheId)) === \false) {
            if ($cache) {
                $lock = new Lock(data_path('lock/menu.lock'));
                $lock->acquire();
            }
            try {
                if (empty($cache) || ($container = $cache->load($cacheId)) === \false) {
                    $container = new NavigationContainer();
                    $container->setPages(
                        static::buildPages(
                            (new Helper\Tree($alias, $domainId))->getTree(\true)
                        )
                    );
                    if ($cache) {
                        $cache->save($container, $cacheId, ['Navigation_Model_Items']);
                    }
                }
            } catch (\Exception $e) {

            }
            if ($cache) {
                $lock->release();
            }
        }

        return $container;
    }
    /**
     * @param array $tree
     * @param NavigationPage|null $parent
     * @return array of NavigationPage
     */
    protected static function buildPages(array $tree, ?NavigationPage $parent = \null)
    {
        $pages = [];

        foreach ($tree as $item) {

            $page = new NavigationPage([
                'id'      => $item['id'],
                'label'   => isset($item['name']) ? $item['name'] : '',
                'alias'   => ($item['alias'] ? $item['alias'] : $item['id']),
                'textDomain' => isset($item['description']) ? $item['description'] : '',
                'visible' => $item['visible'],
                'resource'=> $item['resource'],
                'route'   => $item['route'],
                'reset'   => $item['reset'],
                'qsa'     => $item['qsaData'] ? true : false,
                'targetBlank' => isset($item['targetBlank']) ? (bool) $item['targetBlank'] : false,
                'uri'     => $item['url'],
                'class'   => $item['class'],
            ]);

            if ($item['url'] === \null) {

                $routeData = $item['routeData']
                             ? \json_decode($item['routeData'], \true)
                             : [];

                if ($item['qsaData']) {
                    $qsaData = [];
                    \parse_str($item['qsaData'], $qsaData);
                    $routeData = \array_merge($routeData, $qsaData);
                }

                $page->setRouteParams($routeData);
            }

            if ($item['handlerPosition']) { // custom items after real children
                if (!empty($item['children'])) {
                    $page->addPages(
                        static::buildPages($item['children'], $page)
                    );
                }
            }

            if ($item['handlerId']) {
                $handlers = \explode(',', $item['handlerId']);
                foreach ($handlers as $handler) {
                    if (\class_exists($handler)) {
                        try {
                            \call_user_func(new $handler($page));
                        } catch (\Exception $e) {
                            if (is_debug()) {
                                \trigger_error($handler . ' :: ' . $e->getMessage(), E_USER_WARNING);
                            }
                        }
                    }
                }
            }

            if (!$item['handlerPosition']) { // custom items before real children
                if (!empty($item['children'])) {
                    $page->addPages(
                        static::buildPages($item['children'], $page)
                    );
                }
            }

            $pages[] = $page;
        }

        return $pages;
    }

    /**
     * @param int $domainId
     * @param \Closure $callback
     * @return array
     */
    public static function getActivePage($domainId = null, ?\Closure $callback = null)
    {
        $found = [];

        foreach (static::getMenus($domainId, $callback) as $menu) {
            $found = $menu->findOneBy('active', 1);
            if (!empty($found)) {
                break;
            }
        }

        if (empty($found)) {
            return null;
        }

        return $found;
    }

    /**
     * @param int $domainId
     * @param \Closure $callback
     */
    public static function deactivateMenu($domainId = null, ?\Closure $callback = null)
    {
        foreach (static::getMenus($domainId, $callback) as $menu) {
            $found = $menu->findAllBy('active', 1);
            foreach ($found as $f) {
                $f->setActive(0);
            }
        }
    }
}