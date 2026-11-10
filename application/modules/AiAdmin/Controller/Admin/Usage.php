<?php

namespace AiAdmin\Controller\Admin;

use AiAdmin\Service\BaseController;

class Usage extends BaseController
{
    public function indexAction()
    {
        $today = date('Y-m-d');
        $monthStart = date('Y-m-01');
        $usage = $this->ai()->usage();

        $agentNames = [];
        foreach ($this->safely(fn () => $this->ai()->agents()->all()) as $agent) {
            $agentNames[(string) $agent->id] = $agent->name;
        }

        return [
            'periods' => [
                'Днес' => ['agents' => $this->safely(fn () => $usage->byScope('agent', $today, $today)), 'providers' => $this->safely(fn () => $usage->byScope('provider', $today, $today))],
                'Този месец' => ['agents' => $this->safely(fn () => $usage->byScope('agent', $monthStart, $today)), 'providers' => $this->safely(fn () => $usage->byScope('provider', $monthStart, $today))],
            ],
            'agentNames' => $agentNames,
            'logs' => $this->safely(fn () => $this->ai()->logs()->recent(100)),
            'homeUrl' => $this->url('index'),
        ];
    }
}
