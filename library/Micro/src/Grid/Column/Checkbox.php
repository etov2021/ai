<?php

namespace Micro\Grid\Column;

use Micro\Grid\Column;

class Checkbox extends Column
{
    protected $checkAll = \false;

    protected $translate = \false;

    protected $viewScriptTitle;

    public function __construct($name, array $options = [])
    {
        parent::__construct($name, $options);

        if ($this->checkAll) {
            $this->setTitle('<input class="grid-column-checkbox" data-handler="grid.checkall" type="checkbox" data-rel="' . $this->getName() . '[]" value="1" /> ' . $this->getTitle());
        }
    }

    public function init()
    {
        if ($this->viewScriptTitle) {

            $view = $this->getGrid()->getView();

            if (\null === $view) {
                throw new \Exception('View in the grid renderer is NULL');
            }

            $data = [
                'column' => $this,
            ];

            $this->setTitle((string) $view->partial($this->viewScript, $data));
        }
    }

    public function setCheckAll($value)
    {
        $this->checkAll = (bool) $value;
    }

    public function setViewScriptTitle($value)
    {
        $this->viewScriptTitle = $value;
    }

    public function render()
    {
        $value = parent::render();

        if ($this->viewScript) {
            return $value;
        }

        return '<input class="grid-column-checkbox" type="checkbox" name="' . $this->getName() . '[]" value="' . $value . '" />';
    }
}