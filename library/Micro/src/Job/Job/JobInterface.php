<?php

namespace Micro\Job\Job;

interface JobInterface
{
    public function execute($payload);

    public function success();

    public function fail($message = null);
}