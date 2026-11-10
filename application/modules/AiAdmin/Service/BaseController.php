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
        $response = new RedirectResponse($url);

        return $message === null ? $response : $response->withFlash($message, $type);
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
