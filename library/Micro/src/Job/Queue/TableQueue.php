<?php

namespace Micro\Job\Queue;

use Micro\Database\Table\TableAbstract;
use Micro\Database\Expr;

class TableQueue extends TableAbstract implements QueueInterface
{
    protected $_name = 'Jobs';

    public function init()
    {
        $this->getAdapter()->setProfiler(null);
    }

    public function push($job, $payload, array $params = [])
    {
        return $this->createJob(0, $job, $payload, $params)->save();
    }

    public function pushLater($seconds, $job, $payload, array $params = [])
    {
        return $this->createJob($seconds, $job, $payload, $params)->save();
    }

    protected function createJob($seconds, $job, $payload, array $params = [])
    {
        return $this->createRow([
            'job'        => $job,
            'params'     => json_encode($params),
            'payload'    => json_encode($payload),
            'created'    => new Expr('NOW()'),
            'retry'      => (isset($params['retry']) ? (int) $params['retry'] : 0),
            'retryDelay' => (isset($params['retryDelay']) ? (int) $params['retryDelay'] : 0),
            'priority'   => (isset($params['priority']) ? (int) $params['priority'] : 0),
            'delay'      => $seconds,
        ]);
    }

    public function pop()
    {
        $db  = $this->getAdapter();

        $sql = '
            SELECT *
            FROM Jobs
            WHERE fetched IS NULL AND NOW() >= DATE_ADD(created, INTERVAL ' . $db->quoteIdentifier('delay')  . ' SECOND)
            ORDER BY priority DESC, id ASC
            LIMIT 1
        ';

        $row = $db->fetchRow($sql);

        if ($row) {

            $db->update('Jobs', ['fetched' => new Expr('NOW()')], ['id = ?' => $row['id']]);

            $row['payload'] = json_decode($row['payload'], true);
            $row['params']  = $row['params'] ? json_decode($row['params'], true) : [];
        }

        return $row;
    }

    public function retry($job)
    {
        if ($job && $job['id'] && $job['retry']) {
            $this->pushLater(
                $job['retryDelay'],
                $job['job'],
                $job['payload'],
                ['id' => $job['id'], 'retry' => $job['retry'] - 1, 'priority' => 0] + $job['params']
            );
        }
    }

    public function success($job)
    {
        if ($job && $job['id']) {
            try {
                $this->getAdapter()->update('Jobs', ['success' => 1, 'executed' => new Expr('NOW()')], ['id = ?' => $job['id']]);
            } catch (\Throwable $e) {}
        }
    }

    public function fail($job, $message = null)
    {
        if ($job && $job['id']) {
            try {
                $this->getAdapter()->update('Jobs', ['success' => 0, 'executed' => new Expr('NOW()'), 'message' => $message], ['id = ?' => $job['id']]);
            } catch (\Throwable $e) {}
        }
    }
}