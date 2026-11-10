<?php

namespace App\Helper;

use Micro\Grid\Grid;
use Micro\View\View;
use Micro\Application\Utils;

trait GridPersonalizeTrait
{
    public function gridPersonalizeAction()
    {
        $module = ucfirst(Utils::camelize($this->request->getAttribute('module')));
        $gridName = $this->exportGridName ?? null;

        $grid = new Grid([], module_path($module, '/Resources/grids/admin/' . $gridName . '.php'));

        if ($this->request->isPost()) {
            $visible = $this->request->getPost('visible');
            if ($visible !== null) {
                $checked = $this->request->getPost('checked');
                GridPersonalize::sessionGridColumnOption($gridName, $visible, 'visible', ($checked ? 1 : 0));
            }
            $wrap = $this->request->getPost('wrap');
            if ($wrap !== null) {
                $checked = $this->request->getPost('checked');
                GridPersonalize::sessionGridColumnOption($gridName, $wrap, 'wrap', ($checked ? 1 : 0));
            }
            $width = $this->request->getPost('width');
            if ($width !== null) {
                $value = $this->request->getPost('value');
                GridPersonalize::sessionGridColumnOption($gridName, $width, 'width', $value);
            }
            $orientation = $this->request->getPost('orientation');
            if ($orientation !== null) {
                GridPersonalize::sessionGridOption($gridName, 'orientation', $orientation);
            }
            $size = $this->request->getPost('size');
            if ($size !== null) {
                GridPersonalize::sessionGridOption($gridName, 'size', $size);
            }
        }

        $view = new View('admin/_grid-personalize');

        return $view->assign([
            'grid' => $grid,
            'gridName' => $gridName,
        ]);
    }
}