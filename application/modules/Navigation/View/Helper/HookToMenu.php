<?php

namespace Navigation\View\Helper;

use Micro\Navigation\Page\Page;
use Micro\View\Helper\HelperAbstract;

class HookToMenu extends HelperAbstract
{
    public function __invoke($menuId, $itemId, $page, $visible = \false)
    {
        $container = $this->view->navigation($menuId);

        if ($itemId !== \null) {
            $menuItem = $container->findOneBy('alias', $itemId);
            if ($menuItem === \null) {
                $menuItem = $container->findOneBy('id', $itemId);
                if ($menuItem === \null) {
                    $menuItem = $container;
                }
            }
        } else {
            $menuItem = $container;
        }

        if ($menuItem !== \null) {

            if (\is_scalar($page)) {
                $page = new Page([
                    'label'   => (string) $page,
                    'active'  => 1,
                    'uri'     => route(),
                    'visible' => $visible
                ]);
            } else if (\is_array($page)) {
                if (!isset($page['visible'])) {
                    $page['visible'] = $visible;
                }
                $page = Page::factory($page);
            }

            $menuItem->addPage($page);
        }

        return $page;
    }
}