<?php

namespace App\Helper;

use Micro\Grid\Grid;
use Micro\View\View;
use Micro\Application\Utils;
use App\Helper\Export\Spreadsheet;
use Micro\Http\Response\FileResponse;

trait GridExportTrait
{
    public function exportAction()
    {
        $module = ucfirst(Utils::camelize($this->request->getAttribute('module')));
        $gridName = $this->exportGridName;

        $filters = $this->handleFilters();

        if (!is_array($filters)) {
            $filters = [];
        }

        $model = $this->getModel();

        $this->modifyModel($filters);

        $selectedIds = $this->request->getParam('selectedIds');
        if ($selectedIds) {
            $selectedIds = explode(',', $selectedIds);
            if (!empty($selectedIds)) {
                $selectedIds = array_map('intval', $selectedIds);
                $this->getModel()->addWhere('id', $selectedIds);
            }
        }

        $sort = ($this->sortField ?: $model->getIdentifier()) . '_' . $this->sortDir;
        $sort = $this->request->getParam('sort', $sort);

        $sortParts = explode('_', $sort);

        $orderField = array_shift($sortParts);
        $orderField = $orderField ?: $model->getIdentifier();

        $orderDir = \array_shift($sortParts);
        $orderDir = $orderDir ? \strtoupper($orderDir) : $this->sortDir;

        if (!in_array(\strtoupper($orderDir), ['ASC', 'DESC'])) {
            $orderDir = 'DESC';
        }

        $model->addOrder($orderField, $orderDir);

        $grid = new Grid($model, module_path($module, '/Resources/grids/admin/' . $gridName . '.php'));
        $grid->setIpp(null);

        $this->view->addPath(module_path($module, '/Resources/views/'));

        $grid->setView($this->view);

        if (!isset($filters['forbidden'])) {
            $filters['forbidden'] = 'all';
        }

        $this->modifyGrid($grid, $filters);

        $visibleColumns = [];

        $cell = 'A';
        $columns = [];

        foreach ($grid->getColumns() as $column) {

            $isVisible = GridPersonalize::sessionGridColumnOption($gridName, $column->getName(), 'visible');

            if ($isVisible === null) {
                $isVisible = $column->isVisible();
            }

            if (!$isVisible) {
                continue;
            }

            $visibleColumns[$column->getName()] = true;

            $columns[$cell] = [
                'column' => $column->getName(),
                'options' => $column->getOptions(),
            ];

            $cell++;
        }

        $sheet = [];

        foreach ($grid->getPaginator() as $item) {

            $sheetItem = [];

            foreach ($grid->getColumns() as $column) {
                if (!isset($visibleColumns[$column->getName()])) {
                    continue;
                }
                try {
                    $text = (string) $column->render();
                } catch (\Throwable $e) {
                    $text = '';
                }
                switch($column->getName()) {
                    default:
                        $text = strip_tags($text);
                }
                $sheetItem[$column->getTitle()] = $text;
            }

            $sheet[] = $sheetItem;
        }

        $data = [
            'Sheet 1' => $sheet,
        ];

        $config = [
            'writer' => 'xlsx',
            'dir' => data_path('exports'),
            'sheet_callback' => function ($spreadsheet) use ($sheet, $columns, $gridName) {
                if (!empty($columns)) {
                    foreach ($columns as $column => $columnOptions) {
                        $wrap = GridPersonalize::sessionGridColumnOption($gridName, $columnOptions['column'], 'wrap');
                        if ($wrap === null && isset($columnOptions['options']['export_wrap'])) {
                            $wrap = $columnOptions['options']['export_wrap'];
                        }
                        if ($wrap) {
                            $spreadsheet->getStyle($column . '2:' .  $column . (count($sheet) + 1))->getAlignment()->setWrapText(true);
                        }
                        $width = GridPersonalize::sessionGridColumnOption($gridName, $columnOptions['column'], 'width');
                        if ($width === null && isset($columnOptions['options']['export_width'])) {
                            $width = $columnOptions['options']['export_width'];
                        }
                        if ($width) {
                            $spreadsheet->getColumnDimension($column)->setWidth($width, 'px');
                            $spreadsheet->getColumnDimension($column)->setAutoSize(false);
                        }
                    }
                    $spreadsheet->setSelectedCells('A1');
                    $spreadsheet->getPageSetup()->setOrientation(GridPersonalize::sessionGridOption($gridName, 'orientation', null, 'landscape'));
                    $spreadsheet->getPageSetup()->setPaperSize((int) GridPersonalize::sessionGridOption($gridName, 'size', null, 8));
                }
            },
        ];

        $name = sprintf(str_replace('-', '.', $gridName) . '.%s.%s', time(), 'xlsx');

        $exporter = new Spreadsheet($name, $data, $config);

        \PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder(new \PhpOffice\PhpSpreadsheet\Cell\AdvancedValueBinder());

        return new FileResponse($exporter->export());
    }
}