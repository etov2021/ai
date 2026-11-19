<?php

namespace AiAdmin\Controller\Admin;

use Ai\Exception\AiException;
use Ai\McpServer\McpTokenRepository;
use AiAdmin\Service\BaseController;
use Micro\Form\Form;

class McpAccess extends BaseController
{
    public function indexAction()
    {
        // Токенът идва от createAction през сесията и се показва само веднъж.
        $session = session('AiMcpAccess');
        $newToken = $session->token ?? null;
        unset($session->token);

        $uri = $this->request->getUri();

        return [
            'form' => new Form($this->formPath('knowledge')),
            'tokens' => $this->read(fn () => $this->ai()->mcpTokens()->all()),
            'calls' => $this->read(fn () => $this->ai()->mcpTokens()->recentCalls(30)),
            'documents' => $this->read(fn () => $this->ai()->documents()->all()),
            'agents' => $this->safely(fn () => array_values(array_filter($this->ai()->agents()->all(), static fn ($a) => $a->runnable))),
            'toolCodes' => McpTokenRepository::TOOLS,
            'newToken' => is_string($newToken) ? $newToken : null,
            'endpoint' => $uri->getScheme() . '://' . $uri->getAuthority() . '/mcp',
            'enabled' => (bool) config('ai.mcp_server.enabled', false),
            'createUrl' => $this->url('mcp-access', 'create'),
            'toggleUrl' => $this->url('mcp-access', 'toggle'),
            'deleteUrl' => $this->url('mcp-access', 'delete'),
            'homeUrl' => $this->url('index'),
        ];
    }

    public function createAction()
    {
        $post = $this->post();
        if (!$this->request->isPost() || !(new Form($this->formPath('knowledge')))->isValid($post)) {
            return $this->redirect($this->url('mcp-access'));
        }

        $tools = array_values(array_filter((array) ($post['tools'] ?? []), 'is_string'));
        $all = !empty($post['allDocuments']);
        $ids = array_values(array_map('intval', array_filter((array) ($post['documents'] ?? []), static fn ($v) => is_string($v) && ctype_digit($v))));
        $expires = (string) ($post['expires'] ?? '');

        try {
            if ($expires !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $expires) !== 1) {
                throw new AiException('Невалидна дата на изтичане.');
            }

            $token = $this->ai()->mcpTokens()->create(
                (string) ($post['name'] ?? ''),
                $tools,
                $all ? null : $ids,
                ($post['agent'] ?? '') === '' ? null : (string) $post['agent'],
                !empty($post['maskPii']),
                (int) ($post['rateLimitPerMinute'] ?? 30),
                $expires === '' ? null : $expires . ' 23:59:59',
            );
        } catch (AiException $e) {
            return $this->redirect($this->url('mcp-access'), $e->getMessage(), 'danger');
        }

        session('AiMcpAccess')->token = $token;

        return $this->redirect($this->url('mcp-access'), 'Токенът е създаден. Копирайте го сега: повече няма да се покаже.');
    }

    public function toggleAction()
    {
        return $this->change(fn (int $id) => $this->toggle($id), 'Състоянието е променено.');
    }

    public function deleteAction()
    {
        return $this->change(fn (int $id) => $this->ai()->mcpTokens()->delete($id), 'Токенът е изтрит.');
    }

    private function toggle(int $id): void
    {
        foreach ($this->ai()->mcpTokens()->all() as $row) {
            if ((int) $row['id'] === $id) {
                $this->ai()->mcpTokens()->setActive($id, !$row['active']);
            }
        }
    }

    private function change(\Closure $action, string $message)
    {
        $post = $this->post();

        if ($this->request->isPost() && (new Form($this->formPath('knowledge')))->isValid($post) && ctype_digit((string) ($post['id'] ?? ''))) {
            try {
                $action((int) $post['id']);

                return $this->redirect($this->url('mcp-access'), $message);
            } catch (AiException $e) {
                return $this->redirect($this->url('mcp-access'), $e->getMessage(), 'danger');
            }
        }

        return $this->redirect($this->url('mcp-access'));
    }

    /** Таблиците може да липсват (миграция 014): страницата да се покаже с подсказка, а не да се чупи. */
    private function read(\Closure $read): array|false
    {
        try {
            return $read();
        } catch (\Throwable) {
            return false;
        }
    }
}
