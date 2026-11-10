<?php

namespace App\Helper\Export;

use PhpOffice\PhpSpreadsheet\Spreadsheet as PhpOfficeSpreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**

Writers:

'Xls' => Writer\Xls::class,
'Xlsx' => Writer\Xlsx::class,
'Ods' => Writer\Ods::class,
'Csv' => Writer\Csv::class,
'Html' => Writer\Html::class,
'Tcpdf' => Writer\Pdf\Tcpdf::class,
'Dompdf' => Writer\Pdf\Dompdf::class,
'Mpdf' => Writer\Pdf\Mpdf::class,

Example:

$data = [
    'Sheet 1' => [
        ['id' => 1, 'name' => 'Name 1'],
        ['id' => 2, 'name' => 'Name 2'],
        ['id' => 3, 'name' => 'Name 3'],
        ['id' => 4, 'name' => 'Name 4'],
    ]
];

$config = [
    'writer' => 'csv',
    'writer_options' => [
        'delimiter' => ',',
        'enclosure' => '"',
        'lineEnding' => PHP_EOL,
        'useBOM' => false,
        'includeSeparatorLine' => false,
        'excelCompatibility' => false,
    ],
    'dir' => data_path('exports'),
    'header_callback' => function () {},
    'cell_callback' => function () {},
    'sheet_callback' => function () {},
];

$exporter = new Spreadsheet('test.csv', $data, $config);
$exporter->export();

*/

class Spreadsheet implements ExportInterface
{
    protected $file;
    protected $data = [];
    protected $config = [];

    public function __construct($file, array $data, array $config = [])
    {
        $this->data = $data + $this->data;
        $this->config = $config + $this->config;

        if (isset($this->config['dir'])) {
            $dir = rtrim($this->config['dir'], '\\/');
        } else {
            $dir = data_path('exports');
        }

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $this->file = $dir . DIRECTORY_SEPARATOR . $file;
    }

    public function export()
    {
        $spreadsheet = new PhpOfficeSpreadsheet();

        $sheetIndex = 0;

        $headerCallback = isset($this->config['header_callback']) && is_callable($this->config['header_callback'])
                          ? $this->config['header_callback']
                          : null;

        $cellCallback   = isset($this->config['cell_callback']) && is_callable($this->config['cell_callback'])
                          ? $this->config['cell_callback']
                          : null;

        $sheetCallback  = isset($this->config['sheet_callback']) && is_callable($this->config['sheet_callback'])
                          ? $this->config['sheet_callback']
                          : null;

        foreach ($this->data as $sheetId => $sheetData) {

            $sheet = $spreadsheet->createSheet($sheetIndex);

            $sheet->setTitle(is_int($sheetId) ? 'Worksheet ' . $sheetId : $sheetId);

            if (empty($sheetData)) {
                continue;
            }

            $cell  = 'A';
            $rowId = 1;

            $firstRow = array_keys(current($sheetData));

            foreach ($firstRow as $k => $v) {
                $sheet->setCellValue($cell . $rowId, ($headerCallback ? $headerCallback($v) : $v));
                $sheet->getStyle($cell . $rowId)->getFont()->setBold(true);
                $sheet->getColumnDimension($cell)->setAutoSize(true);
                $cell++;
            }

            $rowId++;

            foreach ($sheetData as $cells) {
                $cell = 'A';
                foreach ($cells as $k => $v) {
                    $sheet->setCellValue($cell . $rowId, ($cellCallback ? $cellCallback($v, $k) : $v));
                    $sheet->getStyle($cell . $rowId)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);
                    $cell++;
                }
                $rowId++;
            }

            $sheet->setSelectedCells('A1');

            if ($sheetCallback) {
                $sheetCallback($sheet);
            }

            $sheetIndex++;
        }

        $spreadsheet->setActiveSheetIndex(0);

        $writerType = isset($this->config['writer']) ? ucfirst($this->config['writer']) : $this->getWriterTypeFromExtension($this->file);
        $writer = IOFactory::createWriter($spreadsheet, $writerType);

        if (isset($this->config['writer_options']) && is_array($this->config['writer_options'])) {
            foreach ($this->config['writer_options'] as $k => $v) {
                $setter = 'set' . ucfirst($k);
                $writer->$setter($v);
            }
        }

        $writer->save($this->file);

        return $this->file;
    }

    protected function getWriterTypeFromExtension($filename)
    {
        $pathinfo = pathinfo($filename);
        if (!isset($pathinfo['extension'])) {
            return null;
        }

        switch (strtolower($pathinfo['extension'])) {
            case 'xlsx': // Excel (OfficeOpenXML) Spreadsheet
            case 'xlsm': // Excel (OfficeOpenXML) Macro Spreadsheet (macros will be discarded)
            case 'xltx': // Excel (OfficeOpenXML) Template
            case 'xltm': // Excel (OfficeOpenXML) Macro Template (macros will be discarded)
                return 'Xlsx';
            case 'xls': // Excel (BIFF) Spreadsheet
            case 'xlt': // Excel (BIFF) Template
                return 'Xls';
            case 'ods': // Open/Libre Offic Calc
            case 'ots': // Open/Libre Offic Calc Template
                return 'Ods';
            case 'htm':
            case 'html':
                return 'Html';
            case 'pdf':
                return 'Tcpdf';
            case 'csv':
                // Do nothing
                // We must not try to use CSV reader since it loads
                // all files including Excel files etc.
                return 'Csv';
            default:
                return null;
        }
    }
}