<?php

namespace AiAdmin\Controller\Admin;

use Ai\Exception\AiException;
use AiAdmin\Service\BaseController;
use Micro\Form\Form;

/** Опашката със фонови задачи: състояние, ръчно пускане, отказ и повторен опит. Изпълнението е в cronjobs/jobs-run.php. */
class Jobs extends BaseController
{
    private const TYPES = [
        'agent' => 'Агент',
        'workflow' => 'Работен поток',
        'embed_documents' => 'Индексиране на документи (вектори)',
        'gmail_poll' => 'Четене на Gmail',
    ];

    public function indexAction()
    {
        $status = (string) $this->request->getParam('status', '');
        $rows = $this->read(fn () => $this->ai()->jobs()->recent(100, $status === '' ? null : $status));

        return [
            'form' => new Form($this->formPath('knowledge')),
            'ready' => $rows !== false,
            'counts' => $this->read(fn () => $this->ai()->jobs()->counts()) ?: [],
            'status' => $status,
            'statusUrls' => ['' => $this->url('jobs')] + array_combine(
                \Ai\Job\JobRepository::STATUSES,
                array_map(fn (string $s): string => $this->url('jobs', 'index', null, ['status' => $s]), \Ai\Job\JobRepository::STATUSES),
            ),
            'rows' => array_map(static function (array $r): array {
                $payload = json_decode((string) $r['payload'], true);
                $payload = is_array($payload) ? $payload : [];
                $r['target'] = (string) ($payload['agent'] ?? $payload['workflow'] ?? '');

                return $r;
            }, $rows ?: []),
            'types' => self::TYPES,
            'agents' => $this->agentOptions(),
            'workflows' => $this->workflowOptions(),
            'enqueueUrl' => $this->url('jobs', 'enqueue'),
            'cancelUrl' => $this->url('jobs', 'cancel'),
            'retryUrl' => $this->url('jobs', 'retry'),
            'homeUrl' => $this->url('index'),
        ];
    }

    public function enqueueAction()
    {
        $post = $this->post();
        if (!$this->request->isPost() || !(new Form($this->formPath('knowledge')))->isValid($post)) {
            return $this->redirect($this->url('jobs'));
        }

        $type = (string) ($post['type'] ?? '');

        try {
            $payload = match ($type) {
                'agent' => ['agent' => $this->agentCode($post), 'input' => $this->input($post)],
                'workflow' => ['workflow' => $this->workflowCode($post), 'input' => $this->input($post)],
                'embed_documents', 'gmail_poll' => [],
                default => throw new AiException('Изберете тип задача.'),
            };

            $id = $this->ai()->jobs()->enqueue($type, $payload, $this->userId());
        } catch (AiException $e) {
            return $this->redirect($this->url('jobs'), $e->getMessage(), 'danger');
        }

        return $this->redirect($this->url('jobs'), 'Задача №' . $id . ' е добавена. Изпълнява се при следващото пускане на работника.');
    }

    public function cancelAction()
    {
        return $this->change(fn (int $id): bool => $this->ai()->jobs()->cancel($id), 'Задачата е отказана.', 'Само чакаща задача може да се откаже.');
    }

    public function retryAction()
    {
        return $this->change(fn (int $id): bool => $this->ai()->jobs()->retry($id), 'Задачата е върната в опашката.', 'Само неуспешна или отказана задача може да се повтори.');
    }

    private function change(\Closure $action, string $ok, string $notAllowed)
    {
        $post = $this->post();

        if ($this->request->isPost() && (new Form($this->formPath('knowledge')))->isValid($post) && ctype_digit((string) ($post['id'] ?? ''))) {
            return $action((int) $post['id'])
                ? $this->redirect($this->url('jobs'), $ok)
                : $this->redirect($this->url('jobs'), $notAllowed, 'warning');
        }

        return $this->redirect($this->url('jobs'));
    }

    /** @param array<string, mixed> $post */
    private function input(array $post): string
    {
        $input = (string) ($post['input'] ?? '');
        if ($input === '' || mb_strlen($input) > 5000) {
            throw new AiException('Входът е задължителен (до 5000 знака).');
        }

        return $input;
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
