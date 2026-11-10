<?php

namespace App\Helper\Export;

interface ExportInterface
{
    public function __construct($file, array $data, array $config = []);

    public function export();
}