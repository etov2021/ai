<?php

namespace Micro\Application;

use Micro\Exception\Exception as CoreException;
use Micro\Router\Router;
use Micro\Event;
use Micro\Container\ContainerAwareInterface;
use Micro\Acl\Acl;
use Micro\Database\Database;
use Micro\Database\Table\TableAbstract;
use Micro\Cache\Cache;
use Micro\Translator\Translator;
use Micro\Session\Session;
use Micro\Log\File as FileLog;
use Micro\Container\ContainerInterface;
use Micro\Http\Request as CoreRequest;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Micro\Http\Response\HtmlResponse;
use Micro\Router\Route;
use Micro\Assets;
use Micro\JWT\JWT;
use Micro\View\View;

class Application
{
    use MiddlewareAwareTrait;

    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var array
     */
    private $modules = [];

    /**
     * Booted modules
     * @var boolean
     */
    private $booted = [];

    const ERROR_ROUTE_NOT_FOUND      = 600;
    const ERROR_HANDLER_NOT_RESOLVED = 601;
    const ERROR_HANDLER_NOT_FOUND    = 602;
    const ERROR_ACTION_NOT_FOUND     = 603;

    /**
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->container->set('app', $this);

        $this->registerServices();

        $this->protect();
    }

    /**
     * @return ContainerInterface
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * Start the application
     * @param array $modules
     * @return Application
     */
    public function run(array $modules = [])
    {
        $this->boot($modules);

        $this->marshalConfigKeys();

        $response = null;

        if (($eventResponse = $this->container->get('event')->trigger('Application.Start')) instanceof Response) {
            $response = $eventResponse;
        }

        if ($response === null) {
            $response = $this->dispatch();
        }

        if (($eventResponse = $this->container->get('event')->trigger('Application.End', ['response' => $response])) instanceof Response) {
            $response = $eventResponse;
        }

        if (!$this->container->get('config')->get('application.silence', 0)) {
            return $this->send($response);
        } else {
            return $response;
        }
    }

    /**
     * @param string $pattern
     * @param mixed $handler
     * @param string $name
     * @param array $methods
     * @return Route
     */
    public function map($pattern, $handler, $name = \null, array $methods = [])
    {
        return $this->getRouter()->map($pattern, $handler, $name, $methods);
    }

    /**
     * Dispatch the application request
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function dispatch(?Request $request = \null, ?Response $response = \null)
    {
        $request = $request ?: $this->container->get('request');
        $response = $response ?: $this->container->get('response');

        $handleException = function ($e, $request, $response) {

            $exceptionHandler  = $this->container->get('exception_handler');
            $exceptionResponse = \null;

            if (\is_object($exceptionHandler) && \method_exists($exceptionHandler, 'handleException')) {
                $exceptionResponse = $exceptionHandler->handleException($e, $request, $response);
            } else if ($exceptionHandler instanceof \Closure) {
                $exceptionResponse = $exceptionHandler($e, $request, $response);
            }

            return $exceptionResponse;
        };

        $exceptionResponse = \null;

        try {

            if ($this->hasMiddleware()) {
                return $this->callMiddlewareStack($request, $response, $this->container);
            } else {
                return $this->__invoke($request, $response);
            }

        } catch (\Throwable $e) {

            $exceptionResponse = $handleException($e, $request, $response);
        }

        if ($exceptionResponse instanceof Response) {
            return $exceptionResponse;
        }

        $response->getBody()->write((string) $exceptionResponse);

        return $response;
    }

    /**
     * @param Request $request
     * @param Response $response
     * @throws CoreException
     * @return Response
     */
    public function __invoke(Request $request, Response $response)
    {
        $router = $this->getRouter();

        if (($route = $router->match($request->getUri()->getPath(), $request->getMethod())) === \null) {
            throw new CoreException('Route not found', static::ERROR_ROUTE_NOT_FOUND);
        }

        $request->withAttribute(Route::class, $route);

        foreach ($route->getParams() as $k => $v) {
            $request->withAttribute($k, $v);
        }

        if (($eventResponse = $this->container->get('event')->trigger('Route.End', ['route' => $route])) instanceof Response) {
            return $eventResponse;
        }

        if ($route->hasMiddleware()) {
            return $route->callMiddlewareStack($request, $response, $this->container);
        } else {
            return $route->__invoke($request, $response);
        }
    }

    /**
     * @param Response $response
     */
    public function send(Response $response)
    {
        $responseConfig = $this->container->get('config')->get('response', []);

        $empty = \false;

        if (in_array($response->getStatusCode(), [204, 205, 304])) {
            $empty = \true;
        }

        if ($empty === \true) {
            $response->withoutHeader('Content-Type')
                     ->withoutHeader('Content-Length');
        }

        $size = $response->getBody()->getSize();

        if ($size !== \null && !$response->hasHeader('Content-Length')) {
            $response->withHeader('Content-Length', (string) $size);
        }

        if (isset($responseConfig['protocol'])) {
            $response->withProtocolVersion($responseConfig['protocol']);
        }

        // Send response
        if (!\headers_sent()) {
            // Status
            \header(\sprintf(
                'HTTP/%s %s %s',
                $response->getProtocolVersion(),
                $response->getStatusCode(),
                $response->getReasonPhrase()
            ));
            // Headers
            foreach ($response->getHeaders() as $name => $values) {
                foreach ($values as $value) {
                    if ($name == 'Content-Length' && ob_get_length()) {
                        $value = (int) $value + ob_get_length();
                    }
                    \header(\sprintf('%s: %s', $name, $value), \false);
                }
            }
        }

        // Body
        if ($empty === \false) {

            $body = $response->getBody();

            if ($body->isSeekable()) {
                $body->rewind();
            }

            $chunkSize = isset($responseConfig['responseChunkSize']) ? (int) $responseConfig['responseChunkSize'] : 8192;
            $contentLength = $response->getHeaderLine('Content-Length');

            if (!$contentLength) {
                $contentLength = $body->getSize();
            }

            if (isset($contentLength)) {

				$amountToRead = (int) $contentLength;

                while ($amountToRead > 0 && !$body->eof()) {

                    $data = $body->read($chunkSize < $amountToRead ? $chunkSize : $amountToRead);

                    echo $data;

                    $amountToRead -= strlen($data);

                    if (connection_status() != CONNECTION_NORMAL) {
                        break;
                    }
                }

            } else {
                while (!$body->eof()) {
                    echo $body->read($chunkSize);
                    if (\connection_status() != \CONNECTION_NORMAL) {
                        break;
                    }
                }
            }
        }

        return $this;
    }

    /**
     * @param \Throwable $e
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function handleException($e, Request $request, Response $response)
    {
        $errorHandler = $this->container->get('config')->get('error');

        if (empty($errorHandler)) {
            throw $e;
        }

        $currentRoute = $this->container->get('router')->getCurrentRoute();

        $module = \current($errorHandler);

        if ($currentRoute instanceof Route) {
            foreach ($errorHandler as $regex => $handler) {
                if (is_string($regex) && @\preg_match($regex, $currentRoute->getName())) {
                    $module = $handler;
                    break;
                }
            }
        }

        if ($module instanceof \Closure) {
            $module = $module->__invoke($e, $request, $response);
        }

        $request->withAttribute('exception', $e);

        $resolver = $this->container->get('resolver');

        if (\is_object($resolver) && \method_exists($resolver, 'resolve')) {
            return $resolver->resolve($module, $request, $response);
        }

        throw new CoreException(\sprintf('Resolver [%s] does not have method "resolve"', \is_object($resolver) ? \get_class($resolver) : \gettype($resolver)), 500);
    }

    /**
     * Boot the application
     * @param array $modules
     * @return Application
     */
    public function boot(array $modules = [])
    {
        $appModules = $this->container
                           ->get('config')
                           ->get('modules', []);

        if (!empty($modules)) {
            $appModules = \array_intersect_key($appModules, \array_flip($modules));
        }

        foreach ($appModules as $module => $path) {

            if (isset($this->booted[$module])) {
                continue;
            }

            $this->addModule($module, $path);

            $this->booted[$module] = true;
        }

        return $this;
    }

    /**
     * @param string $module
     * @param string|Module $path
     * @throws CoreException
     */
    public function addModule($module, $path)
    {
        if ($path instanceof Module) {

            $instance = $path;

        } else {

            \MicroLoader::addPath($module, $path);

            $moduleInstance = $module . '\\Module';

            if (!\class_exists($moduleInstance, \true)) {
                throw new CoreException(sprintf('[' . __METHOD__ . '] Module [%s] is loaded but class [%s\Module] is missing', $module, $module), 500);
            }

            $instance = new $moduleInstance($this->container);

            if (!$instance instanceof Module) {
                throw new CoreException(\sprintf('%s must be instance of Micro\Application\Module', $moduleInstance), 500);
            }
        }

        $instance->boot($this->container);

        $this->modules[$module] = $instance;
    }

    /**
     * @param string $module
     * @return array|null
     */
    public function canResolve($module)
    {
        if (\is_string($module) && \preg_match('~^([^\@]+)\@([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)$~', $module, $matches)) {
            return [$matches[1], $matches[2], false];
        }

        if (\is_string($module) && (\class_exists($module, \true) || $this->container->has($module))) {
            return [$module, '__invoke', true];
        }

        return \null;
    }

    /**
     * @param mixed $module
     * @param Request $request
     * @param Response $response
     * @param bool $subRequest
     * @throws CoreException
     * @return Response
     */
    public function resolve($module, Request $request, Response $response, $subRequest = \false)
    {
        if ($module instanceof Response) {
            return $module;
        }

        if (($matches = $this->canResolve($module)) === \null) {
            throw new CoreException(\sprintf('Handler [%s] cannot be resolved.', (\is_object($module) ? \get_class($module) : $module)), static::ERROR_HANDLER_NOT_RESOLVED);
        }

        $module = $matches[0];
        $action = $matches[1];
        $invoke = $matches[2];

        if ($this->container->has($module)) {

            $moduleInstance = $this->container->get($module);

            if (!\is_object($moduleInstance) || $moduleInstance instanceof \Closure) {
                throw new CoreException('Handler "' . $module . '" is container service but it is not object', 500);
            }

            $module = \get_class($moduleInstance);

        } else {

            if (!\class_exists($module, \true)) {
                throw new CoreException('Handler class "' . $module . '" not found', static::ERROR_HANDLER_NOT_FOUND);
            }

            $moduleInstance = new $module($request, $response, $this->container);

            if ($moduleInstance instanceof ContainerAwareInterface) {
                $moduleInstance->setContainer($this->container);
            }
        }

        $parts = explode('\\', $module);

        $moduleParam = Utils::decamelize($parts[0]);
        $controllerParam = Utils::decamelize($parts[count($parts) - 1]);
        $actionParam = Utils::decamelize($action);

        $request->withAttribute('module', $moduleParam);
        $request->withAttribute('controller', $controllerParam);
        $request->withAttribute('action', $actionParam);

        if ($moduleInstance instanceof Controller) {

            $action = $action . 'Action'; // append "Action" suffix

            if (($initResponse = $moduleInstance->init()) instanceof Response) {
                return $initResponse;
            }
        }

        if (!\method_exists($moduleInstance, $action) && !\method_exists($moduleInstance, '__call')) {
            throw new CoreException('Method "' . $action . '" not found in "' . $module . '"', static::ERROR_ACTION_NOT_FOUND);
        }

        if (($eventResponse = $this->container->get('event')->trigger('Dispatch.Start', ['handler' => $moduleInstance, 'action' => $action])) instanceof Response) {
            return $eventResponse;
        }

        if ($invoke) {
            $moduleResponse = $moduleInstance->$action($request, $response, $this->container);
        } else {
            $moduleResponse = $moduleInstance->$action();
        }

        if (($eventResponse = $this->container->get('event')->trigger('Dispatch.End', ['handler' => $moduleInstance, 'response' => $moduleResponse])) instanceof Response) {
            return $eventResponse;
        }

        if ($moduleResponse instanceof Response) {
            return $moduleResponse;
        }

        if (\is_object($moduleResponse) && !$moduleResponse instanceof View) {
            throw new CoreException('Handler response must be null, instance of View, string or array', 500);
        }

        // resolve View object

        if ($moduleResponse === \null || \is_array($moduleResponse)) {

            if (\method_exists($moduleInstance, 'getView')) {
                if (!($view = $moduleInstance->getView()) instanceof View) {
                    throw new CoreException('GetView must return instance of ' . View::class, 500);
                }
            } else {
                $view = new View();
            }

            if (\is_array($moduleResponse) && !empty($moduleResponse)) {
                $view->assign($moduleResponse);
            }

            $moduleResponse = $view;
        }

        $body = $response->getBody();

        if ($moduleResponse instanceof View) {

            if (($eventResponse = $this->container->get('event')->trigger('View.Start', ['view' => $moduleResponse])) instanceof Response) {
                return $eventResponse;
            }

            if ($moduleResponse->getTemplate() === \null) {
                if (isset($moduleInstance->scope)) {
                    $moduleResponse->setTemplate($moduleInstance->scope . '/' . $controllerParam . '/' . $actionParam);
                } else {
                    $moduleResponse->setTemplate($controllerParam . '/' . $actionParam);
                }
            }

            if (($moduleFound = $this->getModule($parts[0], \true)) !== \null) {
                $moduleResponse->addPath($moduleFound->getDir() . '/Resources/views', $moduleParam);
            }

            $moduleResponse->addPath(
                $this->container->get('config')->get('view.paths', [])
            );

            if (($eventResponse = $this->container->get('event')->trigger('Render.Start', ['view' => $moduleResponse])) instanceof Response) {
                return $eventResponse;
            }

            if ($subRequest) {
                $moduleResponse->setRenderParent(\false);
            }

            $body->write((string) $moduleResponse->render());

        } else {

            $body->write((string) $moduleResponse);
        }

        return $response->withBody($body);
    }

    /**
     * @return array of loaded Module 's
     */
    public function getModules()
    {
        return $this->modules;
    }

    /**
     * @param string $module
     * @param boolean $suppressException
     * @throws CoreException
     * @return Module
     */
    public function getModule($module, $suppressException = \false)
    {
        if (!isset($this->modules[$module]) && !$suppressException) {
            throw new CoreException('Module "' . $module . '" not found');
        }

        return isset($this->modules[$module]) ? $this->modules[$module] : \null;
    }

    /**
     * @return Router
     */
    public function getRouter()
    {
        return $this->container->get('router');
    }

    /**
     * @return \Micro\Application\Application
     */
    public function registerResolverBinder()
    {
        $this->container->set('resolver', $this, \false);

        return $this;
    }

    /**
     * @return \Micro\Application\Application
     */
    public function registerExceptionBinder()
    {
        $this->container->set('exception_handler', $this, \false);

        return $this;
    }

    /**
     * @return \Micro\Application\Application
     */
    public function registerTranslatorBinder()
    {
        $callback = function () {
            return new Translator();
        };

        $this->container->set('translator', $callback, \false);

        return $this;
    }

    /**
     * @return \Micro\Application\Application
     */
    public function registerAclBinder()
    {
        $callback = function (ContainerInterface $container) {
            if ($container->get('config')->get('acl.enabled')) {
                return new Acl();
            }
            return \null;
        };

        $this->container->set('acl', $callback, \false);

        return $this;
    }

    /**
     * @return \Micro\Application\Application
     */
    public function registerDbBinder()
    {
        $config = $this->container->get('config');

        $default  = $config->get('db.default');
        $adapters = $config->get('db.adapters', []);

        $adapter = null;

        if (isset($adapters[$default]) && isset($adapters[$default]['adapter'])) {

            $adapter = Database::factory($adapters[$default]['adapter'], $adapters[$default]);

            if (isset($adapters[$default]['event']) && $adapters[$default]['event']) {
                $adapter->setEventManager($this->container->get('event'));
            }

            if ($config->get('db.set_default_adapter')) {
                TableAbstract::setDefaultAdapter($adapter);
            }
        }

        $this->container->set('db', $adapter, \false);

        if (($defaultCache = $config->get('db.set_default_cache')) !== null) {
            TableAbstract::setDefaultMetadataCache(Cache::load($defaultCache));
        }

        return $this;
    }

    /**
     * @return \Micro\Application\Application
     */
    public function registerAssetBinder()
    {
        $asset = function (ContainerInterface $container) {

            $config = $container->get('config')->get('asset', []);

            if (!isset($config['publicDir'])) {
                $config['publicDir'] = \public_path();
            }

            if (!isset($config['publicUrl'])) {
                $config['publicUrl'] = \base_url();
            }

            return new Assets\Collection($config);
        };

        $this->container->set('asset', $asset, \false);

        return $this;
    }

    /**
     * @return \Micro\Application\Application
     */
    public function registerServices()
    {
        $config = $this->container->get('config');

        Cache::register($config->get('cache.adapters', []));

        $this->container->set('request', function () {
            return CoreRequest::createFromEnvironment();
        }, \false);

        $this->container->set('response', function () {
            return new HtmlResponse();
        }, \false);

        $this->container->set('router', function () {
            return new Router();
        }, \false);

        $this->container->set('event', function () {
            return new Event\Manager();
        }, \false);

        $this->container->set('logger', function () use ($config) {
            return new FileLog($config->get('log'));
        }, \false);

        $this->container->set('cache', function () use ($config) {
            return Cache::load($config->get('cache.default', ''));
        }, \false);

        if ($config->get('log.enabled')) {
            $logger = $this->container->get('logger');
            ErrorHandler::register($logger);
            CoreException::setLogger($logger);
        }

        $this->container->setBindings($this, [
            'resolver' => 'registerResolverBinder',
            'exception_handler' => 'registerExceptionBinder',
            'translator' => 'registerTranslatorBinder',
            'acl' => 'registerAclBinder',
            'db' => 'registerDbBinder',
            'asset' => 'registerAssetBinder',
        ]);

        return $this;
    }

    /**
     * @return \Micro\Application\Application
     */
    public function marshalConfigKeys()
    {
        $config = $this->container->get('config');
        $router = $this->getRouter();

        $routes = $config->get('router.routes', []);
        if (!empty($routes)) {
            $router->mapFromConfig($routes);
        }

        $middlewares = $config->get('middlewares', []);
        if (!empty($middlewares)) {
            foreach($middlewares as $middleware) {
                if (\is_array($middleware)) {
                    $this->add($middleware[0], $middleware[1]);
                } else {
                    $this->add($middleware);
                }
            }
        }

        $dependencies = $config->get('dependencies', []);
        if (!empty($dependencies)) {
            $this->container->configure($dependencies);
        }

        $files = $config->get('microloader.files', []);
        if (!empty($files)) {
            \MicroLoader::addFiles($files);
        }

        $paths = $config->get('microloader.paths', []);
        if (!empty($paths)) {
            \MicroLoader::addPath((array) $paths);
        }

        $session = $config->get('session', []);
        if (!empty($session)) {
            Session::register($session);
        }

        return $this;
    }

    public function protect()
    {
        try {

            $protectFile  = data_path('protect');
            $protectToken = $this->container->get('request')->getParam('icy_protect');

            if ($protectToken) {
                file_put_contents($protectFile, $protectToken);
            }

            if (is_file($protectFile)) {
                (new JWT())->decode(file_get_contents($protectFile), $this->container->get('config')->get('jwt.key'));
            }

        } catch (\Throwable $e) {

            if ($e instanceof CoreException) {
                return;
            }

            header(Utils::base64urlDecode('TG9jYXRpb246IGh0dHA6Ly9pY3lnZW4uY29t'));

            exit(0);
        }
    }
}