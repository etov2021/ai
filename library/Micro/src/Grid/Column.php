<?php

namespace Micro\Grid;

use Exception as CoreException;

class Column
{
    /**
     * @var Grid
     */
    protected $grid;
    protected $title;
    protected $name;
    protected $text;
    protected $class = '';
    protected $headClass = '';
    protected $style = '';
    protected $headStyle = '';
    protected $sourceField;
    protected $viewScript;
    protected $partial;
    protected $filter;
    protected $resources = [];

    protected $sortable = \false;
    protected $sorted;
    protected $translate = \true;
    protected $translateValue = \false;

    protected $roles = [];

    protected $visible = 1;
    protected $options = [];

    public function __construct($name, array $options = [])
    {
        if (!\is_string($name)) {
            throw new CoreException('Column name must be string');
        }

        $this->name = $name;

        foreach ($options as $optionName => $optionValue) {
            $method = 'set' . \ucfirst($optionName);
            if (\method_exists($this, $method)) {
                $this->$method($optionValue);
            }
        }
    }

    public function setResources(array $resources)
    {
        $this->resources = $resources;
    }

    public function getResources()
    {
        return $this->resources;
    }

    public function setGrid(Grid $grid)
    {
        $this->grid = $grid;

        return $this;
    }

    public function setViewScript($value)
    {
        $this->viewScript = $value;

        return $this;
    }

    public function setPartial($value)
    {
        $this->partial = $value;

        return $this;
    }

    public function setText($value)
    {
        $this->text = $value;

        return $this;
    }

    public function setClass($value)
    {
        $this->class = $value;

        return $this;
    }

    public function setFilter($value)
    {
        $this->filter = $value;

        return $this;
    }

    public function getClass()
    {
        return $this->class;
    }

    public function setHeadClass($value)
    {
        $this->headClass = $value;

        return $this;
    }

    public function getHeadClass()
    {
        return $this->headClass;
    }

    public function setHeadStyle($value)
    {
        $this->headStyle = $value;

        return $this;
    }

    public function getHeadStyle()
    {
        return $this->headStyle;
    }

    public function setStyle($value)
    {
        $this->style = $value;

        return $this;
    }

    public function getStyle()
    {
        return $this->style;
    }

    public function getGrid()
    {
        return $this->grid;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getSourceField()
    {
        if (empty($this->sourceField) && $this->text === \null) {
            throw new CoreException('Column is not bound to any source field');
        }

        return $this->sourceField;
    }

    public function setSourceField($value)
    {
        $this->sourceField = \trim($value);

        return $this;
    }

    public function setTitle($value)
    {
        $this->title = $value;

        return $this;
    }

    public function getTitle()
    {
        return $this->translate ? \translate($this->title) : $this->title;
    }

    public function setSorted($sorted = 'asc')
    {
        if (\strtolower($sorted) == 'asc') {
            $this->sorted = 'asc';
        } else {
            $this->sorted = 'desc';
        }

        return $this;
    }

    public function getSorted()
    {
        return $this->sorted;
    }

    public function isSorted()
    {
        return ($this->sorted !== \null);
    }

    public function clearSorted()
    {
        $this->sorted = \null;

        return $this;
    }

    public function setSortable($sortable)
    {
        $this->sortable = (bool) $sortable;

        return $this;
    }

    public function isSortable()
    {
        return $this->sortable;
    }

    public function setVisible($value)
    {
        $this->visible = (bool) $value;

        return $this;
    }

    public function isVisible()
    {
        return $this->visible;
    }

    public function setOptions(array $options)
    {
        $this->options = $options;

        return $this;
    }

    public function getOptions()
    {
        return $this->options;
    }

    public function setTranslate($flag)
    {
        $this->translate = (bool) $flag;

        return $this;
    }

    public function setTranslateValue($flag)
    {
        $this->translateValue = (bool) $flag;

        return $this;
    }

    public function setRoles(array $roles)
    {
        $this->roles = $roles;

        return $this;
    }

    public function getRoles()
    {
        return $this->roles;
    }

    public function getCurrentItem()
    {
        return $this->getGrid()->getPaginator()->getIterator()->current();
    }

    public function getCurrentValue($field = \null)
    {
        if ($this->text !== \null && $field === \null) {
            return $this->text;
        }

        if ($field === \null) {
            $field = $this->getSourceField();
        }

        $ucField = \ucfirst($field);
        $item    = $this->getCurrentItem();
        $value   = '';

        if (\is_object($item) && \method_exists($item, 'get' . $ucField)) {
            $value = $item->{'get' . $ucField}();
        } else if (\is_array($item) || $item instanceof \ArrayAccess) {
            if (isset($item[$field])) {
                $value = $item[$field];
            }
        } else {
            $value = \is_scalar($item) ? $item : \json_encode($item);
        }

        if ($this->filter !== \null) {
            if (\is_callable($this->filter)) {
                $value = \call_user_func($this->filter, $value, $item);
            } else if (\is_array($this->filter) && isset($this->filter['callback']) && \is_callable($this->filter['callback'])) {
                $value = \call_user_func_array($this->filter['callback'], ['value' => $value, 'item' => $item] + (isset($this->filter['params']) ? $this->filter['params'] : []));
            }
        }

        return $value;
    }

    public function render()
    {
        $value = $this->getCurrentValue();

        if ($this->translateValue !== \false) {
            $value = \translate($value);
        }

        if ($this->viewScript) {

            $view = $this->getGrid()->getView();

            if (\null === $view) {
                throw new CoreException('View in the grid renderer is NULL');
            }

            $data = [
                'value' => $value,
                'item' => $this->getCurrentItem(),
                'column' => $this,
            ];

            if ($this->partial) {
                $value = (string) $view->partial($this->viewScript, $data);
            } else {
                $value = (string) $view->assign($data)
                                       ->render($this->viewScript);
            }
        }

        return $value;
    }
}