<?php

namespace Micro\Grid\Renderer;

use Micro\Grid\Grid;
use Micro\Grid\Column;
use Micro\Form\Element;
use Micro\Router\Router;
use Micro\Http\Request;
use Micro\Container\SharedContainer;

class Table implements RendererInterface
{
    /**
     * @var Grid
     */
    protected $grid;

    /**
     * @var Router
     */
    protected $router;

    /**
     * @var Request
     */
    protected $request;

    /**
     * Constructor
     * @param Grid $grid
     * @param Router $router
     * @param Request $request
     */
    public function __construct(Grid $grid, ?Router $router = \null, ?Request $request = \null)
    {
        $this->grid = $grid;

        $container = SharedContainer::getInstance();

        $this->router = $router ?: $container->get('router');
        $this->request = $request ?: $container->get('request');
    }

    protected function createElement($name, array $options)
    {
        $class = 'Micro\Form\Element\\' . \ucfirst($options['type']);

        $instance = null;

        if (class_exists($class, true)) {
            $instance = new $class($name, $options);
        } else if (class_exists($options['type'], true)) {
            $class = $options['type'];
            $instance = new $class($name, $options);
        }

        return $instance;
    }

    public function render()
    {
        $paginator = $this->grid->getPaginator();
        $buttons = $this->grid->getButtons();

        $output = '';
        $buttonsCode = '';

        $requestParams = $this->request->getParams();
        $requestParams = \array_diff_key($requestParams, ($this->request->getPost() ?: []));
        foreach ($requestParams as $key => $requestParam) {
            if (!\is_array($requestParam)) {
                continue;
            }
            unset($requestParams[$key]);
        }

        $output .= '<form class="grid-form" method="post" action="' . $this->router->assemble(\null) . '">';

        if (!empty($buttons)) {
            $buttonsCode = '<div class="grid-buttons">';
            foreach ($buttons as $name => $button) {
                try {
                    if (isset($button['type'])) {
                        $instance = $this->createElement($name, $button);
                        if ($instance) {
                            $buttonsCode .= $instance->render() . ' ';
                        }
                    } else {
                        $buttonsCode .= (new Element\Submit($name, $button))->render() . ' ';
                    }
                } catch (\Throwable $e) {
                    if (is_debug()) {
                        $buttonsCode .= $e->getMessage();
                    }
                }
            }
            $buttonsCode .= '</div>';
            if ($this->grid->getButtonsPlacement() & Grid::PLACEMENT_TOP) {
                $output .= $buttonsCode;
            }
        }

        if (!empty($this->grid->getIppRanger())) {
            $output .= $this->renderIppRanger($requestParams);
        }

        $output .= '<div class="grid-total">' . translate('Total') . ' <strong>' . number_format($this->grid->getPaginator()->getTotalItemCount()) . '</strong> ' . translate('rows') . '</div>';

        $renderPagination = '';

        if ($this->grid->getPaginatorPlacement() & Grid::PLACEMENT_TOP || $this->grid->getPaginatorPlacement() & Grid::PLACEMENT_BOTTOM) {
            $renderPagination = $this->renderPagination();
        }

        if ($this->grid->getPaginatorPlacement() & Grid::PLACEMENT_TOP) {
            $output .= $renderPagination;
        }

        $output .= '<table class="' . ($this->grid->getGridClass() ? $this->grid->getGridClass() : 'table table-bordered table-hover') . '">';

        $output .= '<thead class="' . ($this->grid->getGridTheadClass() ? $this->grid->getGridTheadClass() : 'table-thead') . '">';

        $output .= '<tr class="table-row-head">';

        foreach ($this->grid->getColumns() as $column) {

            if (!$column instanceof Column || !$column->isVisible()) {
                continue;
            }

            if ($column->isSortable()) {

                $sortedClass = 'sorting';

                if ($column->isSorted()) {
                    $sortedClass = 'sorting_' . $column->getSorted();
                }

                $routeParams = \array_merge($requestParams, ['sort' => $column->getName() . '_' . ($column->getSorted() == 'asc' ? 'desc' : 'asc')]);

                $title = '<div class="' . $sortedClass . '" data-location-href="' . $this->router->assemble(\null, $routeParams) . '">' . $column->getTitle() . '</div>';
            } else {
                $title = $column->getTitle();
            }

            $output .= '<th' . ($column->getHeadStyle() ? ' style="' . $column->getHeadStyle() . '"' : '') . ' data-source-head-field="' . $column->getSourceField() . '" class="table-cell-head' . ($column->getHeadClass() ? ' ' . $column->getHeadClass() : '') . '">';
            $output .= $title;
            $output .= '</th>';
        }

        $output .= '</tr>';
        $output .= '</thead>';

        $output .= '<tbody>';

        foreach ($paginator as $key => $page) {

            $output .= '<tr class="table-row">';

            foreach ($this->grid->getColumns() as $column) {

                if (!$column instanceof Column || !$column->isVisible()) {
                    continue;
                }

                
				$label = $column->getTitle();
				if($column instanceof \Micro\Grid\Column\Checkbox) {
					$label = '';
				} 
				
				$output .= '<td data-label="'.$label.'" ' . ($column->getStyle() ? ' style="' . $column->getStyle() . '"' : '') . ' data-source-field="' . $column->getSourceField() . '" class="table-cell' . ($column->getClass() ? ' ' . $column->getClass() : '') . '">';

                try {
                    $output .= (string) $column->render();
                } catch (\Throwable $e) {
                    if (is_debug()) {
                        $output .= $e->getMessage();
                    }
                }

                $output .= '</td>';
            }

            $output .= '</tr>';
        }

        $output .= '</tbody>';

        $output .= '</table>';


        if ($this->grid->getPaginatorPlacement() & Grid::PLACEMENT_BOTTOM) {
            $output .= $renderPagination;
        }

        if (!empty($buttons)) {
            if (($this->grid->getButtonsPlacement() & Grid::PLACEMENT_BOTTOM)) {
                $output .= $buttonsCode;
            }
        }

        $output .= '</form>';

        return $output;
    }

    public function renderPagination()
    {
        if (($this->grid->getPaginator()->count() <= 1) && !$this->grid->getPaginatorAlways()) {
            return "";
        }

        return \pagination($this->grid->getPaginator(), $this->grid->getPaginationViewScript(), \null, $this->grid->getView());
    }

    public function renderIppRanger(array $requestParams)
    {
        $ippRanger = $this->grid->getIppRanger();

        if (!is_array($ippRanger)) {
            return '';
        }

    	$ipp = isset($requestParams['ipp']) ? $requestParams['ipp'] : $this->grid->getIpp();

        $output = '<div class="ipp-ranger">';
        $output .= '<label>';
        $output .= '<select name="ipp" aria-controls="dt_basic" class="form-control ipp-ranger" onchange="window.location.href = this.value;">';
        foreach ($ippRanger as $range) {
            $output .= '<option value="' . route(null, ['ipp' => $range, 'page' => null]) . '"' . ($ipp && $ipp == $range ? ' selected' : '') . '>' . $range . '</option>';
        }
        $output .= '</select>';
        $output .= '</label>';
        $output .= '</div>';

        return $output;
    }
}