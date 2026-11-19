<?php

namespace AiAdmin\Controller\Admin;

use Ai\Exception\AiException;
use Ai\Http\CurlClient;
use Ai\Mcp\McpClient;
use Ai\McpServer\McpTokenRepository;
use Ai\Security\Redactor;
use AiAdmin\Service\BaseController;
use Micro\Form\Form;

class McpAccess extends BaseController
{
    public function indexAction()
    {
        // Токенът идва от createAction през сесията и се показва само веднъж.
        $session = session('AiMcpAccess');
        $newToken = $session->token ?? null;
        $testResult = $session->test ?? null;
        unset($session->token, $session->test);

        return [
            'form' => new Form($this->formPath('knowledge')),
            'testResult' => is_array($testResult) ? $testResult : null,
            'testUrl' => $this->url('mcp-access', 'test'),
            'tokens' => $this->read(fn () => $this->ai()->mcpTokens()->all()),
            'calls' => $this->read(fn () => $this->ai()->mcpTokens()->recentCalls(30)),
            'documents' => $this->read(fn () => $this->ai()->documents()->all()),
            'agents' => $this->safely(fn () => array_values(array_filter($this->ai()->agents()->all(), static fn ($a) => $a->runnable))),
            'toolCodes' => McpTokenRepository::TOOLS,
            'newToken' => is_string($newToken) ? $newToken : null,
            'endpoint' => $this->endpoint(),
            'enabled' => (bool) config('ai.mcp_server.enabled', false),
            'createUrl' => $this->url('mcp-access', 'create'),
            'toggleUrl' => $this->url('mcp-access', 'toggle'),
            'deleteUrl' => $this->url('mcp-access', 'delete'),
            'homeUrl' => $this->url('index'),
        ];
    }

    /** Със start в подпапка (напр. /aiimpl/public) адресът не е /mcp, затова се взема основният път на приложението. */
    private function endpoint(): string
    {
        return server_url(base_url('mcp'));
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

    /** Проба от край до край: сървърът се обажда на собствения си /mcp по HTTP като външен клиент (токенът не се пази). */
    public function testAction()
    {
        $post = $this->post();
        if (!$this->request->isPost() || !(new Form($this->formPath('knowledge')))->isValid($post)) {
            return $this->redirect($this->url('mcp-access'));
        }

        $endpoint = $this->endpoint();
        $token = (string) ($post['testToken'] ?? '');
        $tool = (string) ($post['testTool'] ?? '');
        $rawArgs = (string) ($post['testArgs'] ?? '');

        @set_time_limit(300);

        $lines = [];
        $ok = true;
        try {
            if ($token === '') {
                throw new AiException('Въведете токен.');
            }
            $arguments = $rawArgs === '' ? [] : json_decode($rawArgs, true);
            if (!is_array($arguments)) {
                throw new AiException('Аргументите трябва да са валиден JSON обект, напр. {"query": "договор"}.');
            }

            // Без повторни опити: инструментът не бива да се изпълни два пъти.
            $client = new McpClient(new CurlClient(0), $this->ai()->policy(), $endpoint, $token, 120);
            $names = array_column($client->listTools(), 'name');
            $lines[] = 'Връзката работи. Инструменти за този токен: ' . ($names === [] ? 'няма' : implode(', ', $names));

            if ($tool !== '') {
                $result = $client->callTool($tool, $arguments);
                $ok = !$result['isError'];
                $lines[] = ($ok ? 'Резултат от ' : 'Грешка от ') . $tool . ":\n" . $result['text'];
            }
        } catch (AiException $e) {
            $ok = false;
            $lines[] = Redactor::redact($e->getMessage());
        }

        session('AiMcpAccess')->test = ['ok' => $ok, 'endpoint' => $endpoint, 'text' => implode("\n\n", $lines)];

        return $this->redirect($this->url('mcp-access'));
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
