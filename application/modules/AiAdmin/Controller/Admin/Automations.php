<?php

namespace AiAdmin\Controller\Admin;

use Ai\Automation\Schedule;
use Ai\Exception\AiException;
use AiAdmin\Service\BaseController;
use Micro\Form\Form;

/**
 * Планирани автоматизации: агент или работен поток с зададен вход, който се пуска по разписание.
 * Изпълняват се като системни (без потребител), затова при запис се проверява, че авторът има право да ползва агента.
 */
class Automations extends BaseController
{
    public function indexAction()
    {
        $session = session('AiAutomations');
        $draft = $session->draft ?? null;
        unset($session->draft);

        $rows = $this->read(fn () => $this->ai()->automations()->all());
        $editId = (int) $this->request->getParam('id', 0);
        $edit = (is_array($draft) ? $draft : $this->prefill($rows ?: [], $editId)) + $this->prefill([], 0);

        return [
            'form' => new Form($this->formPath('knowledge')),
            'ready' => $rows !== false,
            'rows' => array_map(function (array $r): array {
                try {
                    $r['schedule'] = Schedule::fromRow($r)->describe();
                } catch (AiException) {
                    $r['schedule'] = 'повредено разписание';
                }

                return $r + ['editUrl' => $this->url('automations', 'index', (string) $r['id'])];
            }, $rows ?: []),
            'edit' => $edit,
            'agents' => $this->agentOptions(),
            'workflows' => $this->workflowOptions(),
            'saveUrl' => $this->url('automations', 'save'),
            'toggleUrl' => $this->url('automations', 'toggle'),
            'runUrl' => $this->url('automations', 'run'),
            'deleteUrl' => $this->url('automations', 'delete'),
            'newUrl' => $this->url('automations'),
            'jobsUrl' => $this->url('jobs'),
            'homeUrl' => $this->url('index'),
        ];
    }

    public function saveAction()
    {
        $post = $this->post();
        if (!$this->request->isPost() || !(new Form($this->formPath('knowledge')))->isValid($post)) {
            return $this->redirect($this->url('automations'));
        }

        try {
            $kind = (string) ($post['kind'] ?? '');
            $target = match ($kind) {
                // agentCode() пуска само агентите, които авторът има право да изпълнява.
                'agent' => $this->agentCode($post),
                'workflow' => $this->workflowCode($post),
                default => throw new AiException('Изберете агент или работен поток.'),
            };

            $id = ctype_digit((string) ($post['automationId'] ?? '')) && (int) $post['automationId'] > 0 ? (int) $post['automationId'] : null;
            $this->ai()->automations()->save(
                $id,
                (string) ($post['name'] ?? ''),
                $kind,
                $target,
                (string) ($post['input'] ?? ''),
                $this->schedule($post),
                !empty($post['active']),
                $this->userId(),
            );
        } catch (AiException $e) {
            // Въведеното се пази, за да не се пише наново.
            session('AiAutomations')->draft = array_intersect_key($post, array_flip(['automationId', 'name', 'kind', 'agent', 'workflow', 'input', 'scheduleType', 'intervalMinutes', 'scheduleTime', 'weekdays', 'active'])) + ['active' => '', 'weekdays' => []];

            return $this->redirect($this->url('automations'), $e->getMessage(), 'danger');
        }

        return $this->redirect($this->url('automations'), 'Автоматизацията е записана.');
    }

    public function toggleAction()
    {
        return $this->change(function (int $id): ?string {
            $row = $this->ai()->automations()->find($id);
            $this->ai()->automations()->setActive($id, $row !== null && !$row['active']);

            return 'Състоянието е променено.';
        });
    }

    public function runAction()
    {
        return $this->change(function (int $id): ?string {
            $jobId = $this->ai()->automationScheduler()->runNow($id, $this->userId());
            if ($jobId === null) {
                throw new AiException('Предишното изпълнение още не е приключило или автоматизацията липсва.');
            }

            return 'Добавена е задача №' . $jobId . '. Изпълнява се при следващото пускане на работника.';
        });
    }

    public function deleteAction()
    {
        return $this->change(function (int $id): ?string {
            $this->ai()->automations()->delete($id);

            return 'Автоматизацията е изтрита.';
        });
    }

    private function change(\Closure $action)
    {
        $post = $this->post();

        if ($this->request->isPost() && (new Form($this->formPath('knowledge')))->isValid($post) && ctype_digit((string) ($post['id'] ?? ''))) {
            try {
                return $this->redirect($this->url('automations'), (string) $action((int) $post['id']));
            } catch (AiException $e) {
                return $this->redirect($this->url('automations'), $e->getMessage(), 'danger');
            }
        }

        return $this->redirect($this->url('automations'));
    }

    /** @param array<string, mixed> $post */
    private function schedule(array $post): Schedule
    {
        return match ((string) ($post['scheduleType'] ?? '')) {
            Schedule::EVERY => Schedule::every((int) ($post['intervalMinutes'] ?? 0)),
            Schedule::DAILY => Schedule::daily((string) ($post['scheduleTime'] ?? '')),
            Schedule::WEEKLY => Schedule::weekly((string) ($post['scheduleTime'] ?? ''), array_values(array_filter((array) ($post['weekdays'] ?? []), 'is_string'))),
            default => throw new AiException('Изберете разписание.'),
        };
    }

    /** @param array<string, mixed> $post */
    private function workflowCode(array $post): string
    {
        $code = (string) ($post['workflow'] ?? '');
        if (!array_key_exists($code, $this->workflowOptions())) {
            throw new AiException('Изберете работен поток.');
        }

        return $code;
    }

    /** @return array<string, string> */
    private function workflowOptions(): array
    {
        $options = [];
        foreach ($this->safely(fn () => $this->ai()->workflows()->all()) as $workflow) {
            if ($workflow->active) {
                $options[$workflow->code] = $workflow->name;
            }
        }

        return $options;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<string, mixed>
     */
    private function prefill(array $rows, int $id): array
    {
        foreach ($rows as $r) {
            if ((int) $r['id'] === $id) {
                return [
                    'automationId' => (string) $r['id'], 'name' => $r['name'], 'kind' => $r['kind'],
                    'agent' => $r['kind'] === 'agent' ? $r['target'] : '', 'workflow' => $r['kind'] === 'workflow' ? $r['target'] : '',
                    'input' => $r['input'], 'scheduleType' => $r['scheduleType'], 'intervalMinutes' => (string) ($r['intervalMinutes'] ?? ''),
                    'scheduleTime' => (string) ($r['scheduleTime'] ?? ''), 'weekdays' => array_filter(explode(',', (string) $r['weekdays']), 'strlen'),
                    'active' => $r['active'] ? '1' : '',
                ];
            }
        }

        return ['automationId' => '', 'name' => '', 'kind' => 'agent', 'agent' => '', 'workflow' => '', 'input' => '', 'scheduleType' => 'daily', 'intervalMinutes' => '60', 'scheduleTime' => '08:00', 'weekdays' => ['1', '2', '3', '4', '5'], 'active' => '1'];
    }

    /** Таблиците може да липсват (миграция 019): страницата да се покаже с подсказка, а не да се чупи. */
    private function read(\Closure $read): array|false
    {
        try {
            return $read();
        } catch (\Throwable) {
            return false;
        }
    }
}
