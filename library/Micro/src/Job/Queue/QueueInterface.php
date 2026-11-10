<?php

namespace Micro\Job\Queue;

interface QueueInterface
{
    public function push($job, $payload, array $params = []);

    public function pushLater($seconds, $job, $payload, array $params = []);

    public function pop();

    public function retry($job);

    public function success($job);

    public function fail($job, $message = null);
}