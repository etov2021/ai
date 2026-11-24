<?php

namespace AiAdmin\Service;

use Ai\Exception\AiException;
use Micro\Acl\AuthorizeInterface;
use Micro\Application\Controller;
use Micro\Application\Utils;
use Micro\Http\Response\RedirectResponse;

/** Общи помощници за контролерите. Всяко публично *Action в наследниците е отделен ACL ресурс. */
abstract class BaseController extends Controller implements AuthorizeInterface
{
    public $scope = 'admin';

    /** Контролер, който записва в одита сам и с повече подробности, го изключва тук. */
    protected bool $autoAudit = true;

    protected function ai(): AiServices
    {
        return AiServices::get();
    }

    protected function url(string $controller, string $action = 'index', ?string $id = null, array $query = []): string
    {
        return route_reset('admin', ['module' => 'ai-admin', 'controller' => $controller, 'action' => $action, 'id' => $id] + $query);
    }

    protected function redirect(string $url, ?string $message = null, string $type = 'success'): RedirectResponse
    {
        $this->auditPost($message, $type);
        $response = new RedirectResponse($url);

        return $message === null ? $response : $response->withFlash($message, $type);
    }

    /** Записва в одита ръчно действие, което не минава през redirect(). */
    protected function audit(string $action, ?string $entityType = null, ?string $entityId = null, array $details = [], string $result = 'ok'): void
    {
        $this->ai()->audit()->record($action, $entityType, $entityId, $details, $result);
    }

    /**
     * Всяка промяна от админ формите (POST) завършва с пренасочване, затова одитът е тук, а не във всеки контролер.
     * От данните на формата се взимат само познати безопасни полета; тайни (ключове, пароли) никога не се записват.
     */
    private function auditPost(?string $message, string $type): void
    {
        if (!$this->autoAudit || !$this->request->isPost()) {
            return;
        }

        $controller = (string) $this->request->getAttribute('controller', '');
        // Съобщенията в чата се записват като изпълнения на агенти (agent.run), не като форми.
        if ($controller === '' || $controller === 'chat') {
            return;
        }

        $params = [];
        foreach (['id', 'code', 'name', 'agent', 'provider', 'model', 'tool', 'scope', 'type', 'kind', 'workflow'] as $field) {
            $value = ((array) $this->request->getPost())[$field] ?? $this->request->getParam($field);
            if (is_scalar($value) && (string) $value !== '') {
                $params[$field] = (string) $value;
            }
        }

        $this->ai()->audit()->record(
            'admin.' . $controller . '.' . (string) $this->request->getAttribute('action', ''),
            $controller,
            $params['id'] ?? $params['code'] ?? null,
            ['params' => $params, 'message' => $message],
            $type === 'danger' ? 'error' : 'ok',
        );
    }

    /** Изпълнява четене; грешките на библиотеката се показват като съобщение, а страницата се рисува със стойността по подразбиране. */
    protected function safely(\Closure $read, mixed $default = [])
    {
        try {
            return $read();
        } catch (AiException $e) {
            flash()->setMessage($e->getMessage(), 'danger');

            return $default;
        }
    }

    /** @return array<string, mixed> */
    protected function post(): array
    {
        return Utils::arrayMapRecursive('trim', $this->request->getPost());
    }

    protected function userId(): ?int
    {
        $identity = identity();

        return $identity ? (int) $identity->getId() : null;
    }

    protected function formPath(string $name): string
    {
        return module_path('AiAdmin') . '/Resources/forms/admin/' . $name . '.php';
    }

    /** @return array<string, string> код => име на агентите, които могат да се изпълняват */
    protected function agentOptions(): array
    {
        $options = [];
        $userId = $this->userId();
        foreach ($this->safely(fn () => $this->ai()->agents()->all()) as $agent) {
            if ($agent->runnable && $this->safely(fn () => $this->ai()->access()->canRunAgent($agent->code, $userId), true)) {
                $options[$agent->code] = $agent->name . ' (' . $agent->providerCode . ' / ' . $agent->model . ')';
            }
        }

        return $options;
    }

    /**
     * @param array<string, mixed> $post
     * @throws AiException ако агентът не е от разрешените
     */
    protected function agentCode(array $post): string
    {
        $code = (string) ($post['agent'] ?? '');
        if (!array_key_exists($code, $this->agentOptions())) {
            throw new AiException('Изберете агент.');
        }

        return $code;
    }

    protected static function decimalOrNull(mixed $value): ?float
    {
        $value = trim((string) $value);

        return $value === '' ? null : (float) str_replace(',', '.', $value);
    }

    protected static function intOrNull(mixed $value): ?int
    {
        $value = trim((string) $value);

        return $value === '' ? null : (int) $value;
    }
}
