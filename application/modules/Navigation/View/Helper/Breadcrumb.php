<?php

namespace Navigation\View\Helper;

use Micro\View\Helper\HelperAbstract;
use Micro\Navigation\Page\AbstractPage;
use Micro\View\View;

class Breadcrumb extends HelperAbstract
{
    protected $breadcrumbs = [];

    public function __construct(View $view)
    {
        parent::__construct($view);

        $this->breadcrumbs[] = [
            'href'  => route_reset('home'),
            'label' => translate('Начало'),
        ];
    }

    public function __invoke($breadcrumbs = null)
    {
        if ($breadcrumbs !== null) {
            $this->add($breadcrumbs);
        }

        return $this;
    }

    public function get($domain = null, $callback = null)
    {
        if (count($this->breadcrumbs) <= 1) {
            if (($found = Navigation::getActivePage($domain, $callback)) !== null) {
                $pages = [];
                do {
                    $pages[] = $found;
                    $found = $found->getParent();
                } while ($found instanceof AbstractPage);
                foreach (array_reverse($pages) as $page) {
                    $this->breadcrumbs[] = [
                        'href' => $page->getHref(),
                        'label' => $page->getLabel(),
                    ];
                }
            }
        }

        return $this->breadcrumbs;
    }

    public function add($breadcrumbs)
    {
        if (is_scalar($breadcrumbs)) {
            $this->breadcrumbs[] = ['href' => '#', 'label' => $breadcrumbs];
        } else if (is_array($breadcrumbs)) {
            foreach ($breadcrumbs as $v) {
                if (isset($v['href']) && isset($v['label'])) {
                    $this->breadcrumbs[] = ['href' => $v['href'], 'label' => $v['label']];
                } else if (is_scalar($v)) {
                    $this->breadcrumbs[] = ['href' => '#', 'label' => $v];
                }
            }
        }
    }
}