<?php

namespace Micro\Job;

class Worker
{
    /**
     * @var Queue\QueueInterface
     */
    protected $queue;

    protected $running = true;

    protected $config = [
        'sleep'         => 3,
        'sleepAfterJob' => 0,
    ];

    public function __construct(Queue\QueueInterface $queue, array $config = [])
    {
        $this->queue  = $queue;
        $this->config = $config + $this->config;
    }

    public function getQueue()
    {
        return $this->queue;
    }

    public function getConfig()
    {
        return $this->config;
    }

    public function run()
    {
        try {

            $this->log('Running ...');

            while ($this->running) {

                //echo $this->log(memory_get_usage() / 1024 / 1024);

                try {

                    $job = $this->queue->pop();

                } catch (\Throwable $e) {

                    $this->log(sprintf('Error fetching job: [%s]', $e->getMessage()));

                    $job = false;
                }

                if ($job === false) {

                    if ($this->config['sleep']) {
                        sleep($this->config['sleep']);
                    }

                    continue;
                }

                $handler = $job['job'];

                if (class_exists($handler)) {

                    $instance = new $handler($job['params']);

                    if ($instance instanceof Job\JobInterface) {

                        $instance->worker = $this;

                        try {

                            $this->log(sprintf('Executing job [%d] [%s] ... ', $job['id'], $handler), false);

                            if ($instance->execute($job['payload'])) {

                                $this->log("success");

                                $instance->success();

                                $this->queue->success($job);

                            } else {

                                throw new \Exception('no result');
                            }

                        } catch (\Throwable $e) {

                            $this->log('fail: ' . $e->getMessage());

                            $instance->fail($e->getMessage());

                            $this->queue->fail($job, $e->getMessage());

                            if ($job['retry']) {
                                $this->queue->retry($job);
                            }
                        }
                    } else {

                        $message = sprintf('Job [%s] fail: Handler must be instanceof [%s]', $handler, Job\JobInterface::class);

                        $this->log($message);

                        $this->queue->fail($job, $message);
                    }

                    $instance = null;

                } else {

                    $message = sprintf('Job [%s] fail: Invalid handler class', $handler);

                    $this->log($message);

                    $this->queue->fail($job, $message);
                }

                if ($this->config['sleepAfterJob']) {
                    sleep($this->config['sleepAfterJob']);
                }
            }

        } catch (\Throwable $e) {

            $this->log($e->getMessage());
        }
    }

    public function stop()
    {
        $this->running = false;
    }

    protected function log($message, $nl = true)
    {
        echo $message . ($nl ? PHP_EOL : '');
    }
}