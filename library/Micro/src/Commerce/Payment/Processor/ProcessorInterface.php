<?php

namespace Micro\Commerce\Payment\Processor;

interface ProcessorInterface
{
    public function setDescription($description);

    public function getDescription();

    public function getErrors();

    public function isError();

    public function getStatus();

    public function getResponse();

    public function processPayment();

    public function doCallback($callback, array $params);
}