<?php

namespace Micro\Grid;

use Micro\Exception\Exception as CoreException;
use Micro\Paginator\Paginator;
use Micro\Grid\Renderer\RendererInterface;
use Micro\View\View;
use Micro\View\ViewAwareInterface;
use Micro\Acl\RoleInterface;
use Micro\Acl\Acl;

class Grid implements ViewAwareInterface
{
    const PLACEMENT_NONE   = 0;
    const PLACEMENT_BOTTOM = 0x1;
    const PLACEMENT_TOP    = 0x2;
    const PLACEMENT_BOTH   = 0x3;

    protected $columns = [];

    protected $buttons = [];

    protected $paginator;

    protected $gridClass;

    protected $gridTheadClass;

    protected $paginationViewScript = 'paginator::default';

    protected $paginatorAlways = \true;

    protected $paginatorPlacement = self::PLACEMENT_BOTTOM;

    protected $buttonsPlacement = self::PLACEMENT_TOP;

    protected $sortedColumn;

    /**
     * @var RendererInterface
     */
    protected $renderer;

    /**
     * @var View
     */
    protected $view;

    protected $ippRanger = [10, 25, 50, 100];

    public function __construct($paginator, $options = [])
    {
        if ($paginator !== \null && !($paginator instanceof Paginator)) {
            try {
                $paginator = new Paginator($paginator);
            } catch (\Throwable $e) {
                throw new CoreException('Invalid paginator in ' . __METHOD__);
            }
        }

        $this->paginator = $paginator;

        if (\is_string($options) && \file_exists($options)) {
            $options = include $options;
            $options = \is_array($options) ? $options : [];
        }

        if (!\is_array($options)) {
            throw new CoreException('Invalid options in ' . __METHOD__ . ' (' . (\is_scalar($options) ? $options : \json_encode($options)) . ')');
        }

        $this->setOptions($options);
    }

    public function setOptions(array $options)
    {
        foreach ($options as $k => $v) {
            $method = 'set' . ucfirst($k);
            if (\method_exists($this, $method)) {
                $this->$method($v);
            }
        }
    }

    public function setView(View $view)
    {
        $this->view = $view;

        return $this;
    }

    public function getView()
    {
        return $this->view;
    }

    public function getRenderer()
    {
        if ($this->renderer === \null) {
            $this->renderer = new Renderer\Table($this);
        }

        return $this->renderer;
    }

    public function setRenderer($renderer)
    {
        if (\is_string($renderer) && \class_exists($renderer , \true)) {
            $renderer = new $renderer($this);
        }

        if (!$renderer instanceof RendererInterface) {
            throw new CoreException('Invalid renderer: ' . __METHOD__);
        }

        $this->renderer = $renderer;
    }

    public function getGridClass()
    {
        return $this->gridClass;
    }

    public function setGridClass($class)
    {
        $this->gridClass = $class;
    }

    public function getGridTheadClass()
    {
        return $this->gridTheadClass;
    }

    public function setGridTheadClass($gridTheadClass)
    {
        $this->gridTheadClass = $gridTheadClass;
    }

    public function getPaginationViewScript()
    {
        return $this->paginationViewScript;
    }

    public function setPaginationViewScript($value)
    {
        $this->paginationViewScript = $value;

        return $this;
    }

    public function getPaginatorAlways()
    {
        return $this->paginatorAlways;
    }

    public function setPaginatorAlways($value)
    {
        $this->paginatorAlways = $value;

        return $this;
    }

    /**
     * @param Column $column
     * @return $this
     */
    public function setSortedColumn(Column $column)
    {
        $this->sortedColumn = $column;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getSortedColumn()
    {
        return $this->sortedColumn;
    }

    public function setSorted($columnName, $direction = 'asc')
    {
        $column = $this->getColumn($columnName);

        if (!$column instanceof Column || !$column->isSortable()) {
            return \false;
        }

        if ($this->getSortedColumn()) {
            $this->getSortedColumn()->clearSorted();
        }

        $column->setSortable(\true);

        $column->setSorted($direction);

        $this->setSortedColumn($column);

        return true;
    }

    public function setPaginatorPlacement($placement = self::PLACEMENT_BOTTOM)
    {
        if (\is_numeric($placement)) {
            $this->paginatorPlacement = (int) $placement;
        } else if (\is_string($placement)) {
            $const = 'PLACEMENT_' . \strtoupper($placement);
            if (!\defined("self::$const")) {
                throw new CoreException('Invalid paginator placement');
            }
            $this->paginatorPlacement = \constant("self::$const");
        } else {
            throw new CoreException('Invalid paginator placement');
        }

        return $this;
    }

    public function getPaginatorPlacement()
    {
        return $this->paginatorPlacement;
    }

    public function setButtonsPlacement($placement = self::PLACEMENT_TOP)
    {
        if (\is_numeric($placement)) {
            $this->buttonsPlacement = (int) $placement;
        } else if (\is_string($placement)) {
            $const = 'PLACEMENT_' . \strtoupper($placement);
            if (!\defined("self::$const")) {
                throw new CoreException('Invalid paginator placement');
            }
            $this->buttonsPlacement = \constant("self::$const");
        } else {
            throw new CoreException('Invalid buttons placement');
        }

        return $this;
    }

    public function getButtonsPlacement()
    {
        return $this->buttonsPlacement;
    }

    /**
     * @param string $name
     * @return Column|\null
     */
    public function getColumn($name)
    {
        if (isset($this->columns[$name])) {
            return $this->columns[$name];
        }

        return \null;
    }

    public function getColumns()
    {
        return $this->columns;
    }

    public function setColumns(array $columns)
    {
        $this->clearColumns();

        $this->addColumns($columns);

        return $this;
    }

    public function addColumns(array $columns)
    {
        foreach ($columns as $key => $spec) {

            $name = \null;

            if (!\is_numeric($key)) {
                $name = $key;
            }

            if (\is_string($spec) || ($spec instanceof Column)) {
                $this->addColumn($spec, $name);
                continue;
            }

            $options = [];

            $type = 'column';

            if (\is_array($spec)) {

                if (isset($spec['type'])) {
                    $type = $spec['type'];
                } else {
                    $type = 'column';
                }

                if (isset($spec['name'])) {
                    $name = $spec['name'];
                }

                if (isset($spec['options'])) {
                    $options = $spec['options'];
                }
            }

            $this->addColumn($type, $name, $options);
        }

        return $this;
    }

    public function addColumn($type, $name = \null, $options = \null)
    {
        $columnObj = \null;

        if (\is_string($type)) {
            if (\null === $name) {
                throw new CoreException('Columns specified by string must have an accompanying name');
            }
            $columnObj = $this->createColumn($type, $name, $options);
        } elseif ($type instanceof Column) {
            if (\null === $name) {
                $name = $type->getName();
            }
            $columnObj = $type;
        }

        if (\null === $columnObj) {
            throw new CoreException('Cannot add NULL column to grid');
        }

        if (!($columnObj instanceof Column)) {
            throw new CoreException('Trying to add invalid column to grid');
        }

        $columnObj->setGrid($this);

        if (!$this->hasAccessToResources($columnObj->getResources())) {
            return $this;
        }

        if (!$this->hasAccessToRoles($columnObj->getRoles())) {
            return $this;
        }

        $this->columns[$name] = $columnObj;

        return $this;
    }

    public function createColumn($type, $name, ?array $options = \null)
    {
        if (!\is_string($type)) {
            throw new CoreException('Column type must be a string indicating type');
        }

        if ($options === \null) {
            $options = [];
        }

        $columnType = \ucfirst($type);

        if ($columnType === 'Column') {
            $columnClass = 'Micro\Grid\\' . $columnType;
        } else {
            $columnClass = 'Micro\Grid\Column\\' . $columnType;
        }

        if (\class_exists($columnClass, \true)) {
            return new $columnClass($name, $options);
        }

        $columnClass = $type;

        if (\class_exists($type, \true)) {
            return new $type($name, $options);
        }

        throw new CoreException(\sprintf('[%s] Class [%s] does not exists!', __METHOD__, $columnClass));
    }

    public function removeColumn($name)
    {
        if (isset($this->columns[(string) $name])) {
            unset($this->columns[(string) $name]);
        }

        return $this;
    }

    public function clearColumns()
    {
        $this->columns = [];

        return $this;
    }

    public function setButtons(array $buttons)
    {
        $this->clearButtons();

        $this->addButtons($buttons);

        return $this;
    }

    public function getButtons()
    {
        return $this->buttons;
    }

    public function addButtons(array $buttons)
    {
        foreach ($buttons as $name => $button) {

            if (\is_numeric($name)) {
                throw new CoreException('Invalid button configuration passed');
            }

            if (!isset($button['value']) && (!isset($button['type']) || in_array($button['type'], ['button', 'submit']))) {
                $button['value'] = \ucfirst($name);
            }

            $this->addButton($name, $button);
        }

        return $this;
    }

    public function addButton($name, ?array $options = \null)
    {
        if ($options === \null) {
            $options = [];
        }

        if (isset($options['resources']) && is_array($options['resources'])) {
            if (!$this->hasAccessToResources($options['resources'])) {
                return $this;
            }
        }

        $this->buttons[$name] = $options;

        return $this;
    }

    public function clearButtons()
    {
        $this->buttons = [];

        return $this;
    }

    public function removeButton($name)
    {
        if (isset($this->buttons[(string) $name])) {
            unset($this->buttons[(string) $name]);
        }

        return $this;
    }

    protected function hasAccessToResources(array $resources)
    {
        if (empty($resources)) {
            return true;
        }

        foreach ($resources as $resource) {
            if (is_allowed($resource)) {
                return true;
            }
        }

        return false;
    }

    protected function hasAccessToRoles(array $roles)
    {
        if (empty($roles)) {
            return true;
        }

        $acl = app('acl', true);

        if (!$acl instanceof Acl) {
            return true;
        }

        $identity = identity();

        if (!$identity instanceof RoleInterface) {
            return true;
        }

        $aclRoles = $acl->getAllRoles($identity->getRoleId());

        foreach ($roles as $role) {
            if (isset($aclRoles[$role])) {
                return true;
            }
        }

        return false;
    }

    public function setPageNumber($page)
    {
        $this->paginator->setPageNumber((int) $page);

        return $this;
    }

    public function setIpp($ipp)
    {
        $this->paginator->setIpp($ipp);

        return $this;
    }

    public function getIpp()
    {
        return  $this->paginator->getIpp();
    }

    public function getPaginator()
    {
        return $this->paginator;
    }

    public function setPaginator($paginator)
    {
        if ($paginator !== \null && !($paginator instanceof Paginator)) {
            try {
                $paginator = new Paginator($paginator);
            } catch (\Throwable $e) {
                throw new CoreException('Invalid paginator in ' . __METHOD__);
            }
        }

        $this->paginator = $paginator;

        return $this;
    }

    public function render()
    {
        return $this->getRenderer()->render();
    }

    public function renderPagination()
    {
        return $this->getRenderer()->renderPagination();
    }

    public function __toString()
    {
        try {
            return $this->render();
        } catch (\Throwable $e) {
            if (is_debug()) {
                return $e->getMessage();
            }
            return '';
        }
    }

    public function getIppRanger()
    {
        return $this->ippRanger;
    }

    public function setIppRanger($ippRanger = [])
    {
        if ($ippRanger === \false) {
            $this->ippRanger = [];
            return $this;
        }

        if (!empty($ippRanger)) {
            if (!is_array($ippRanger)) {
                $ippRanger = array($ippRanger);
            }
            $this->ippRanger = $ippRanger;
            $ipp = $this->paginator->getIpp();

            if ($ipp) {
                if (!in_array($ipp, $ippRanger)) {
                    $this->ippRanger[] = $ipp;
                    asort($this->ippRanger);
                }
            } else {
                $ipp = $ippRanger[0];
            }

            $this->paginator->setIpp($ipp);
        }

        return $this;
    }
}