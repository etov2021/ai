<?php

use Micro\View\View;
use Micro\Exception\Exception as CoreException;
use Micro\Http\Response\JsonResponse;
use Micro\Http\Response\RedirectResponse;
use Micro\Paginator\Paginator;
use Micro\Acl\RoleInterface;
use Micro\Auth\Auth;
use Micro\Helper\Flash;
use Micro\Container\SharedContainer;
use Micro\Escaper\Escaper;
use Micro\Application\Utils;
use Micro\Http\Response;
use Micro\Http\TempStream;
use Micro\Http\Request;
use Micro\Transliterate\Transliterate;
use Micro\Translator\Language\LanguageInterface;
use Micro\Lock\Lock;
use Micro\Cache;
use Micro\Session\SessionNamespace;

if (!function_exists('app')) {
    function app($service = 'app', $checkExists = false)
    {
        $container = SharedContainer::getInstance();

        if ($container === null) {
            throw new RuntimeException('There is no set container instance in SharedContainer', 500);
        }

        try {
            return $container->get($service);
        } catch (Throwable $e) {
            if ($checkExists) {
                return null;
            }
            throw $e;
        }
    }
}

if (!function_exists('app_path')) {
    function app_path($path = null)
    {
        $appPath = realpath(SharedContainer::getInstance()->get('config')->get('application.path', 'application'));

        if ($path !== null && $path !== '/' && $path !== '\\' && $path !== '') {
            $appPath .= DIRECTORY_SEPARATOR . trim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
        }

        return $appPath;
    }
}

if (!function_exists('public_path')) {
    function public_path($path = null)
    {
        $publicPath = realpath(SharedContainer::getInstance()->get('config')->get('application.public_path', 'public'));

        if ($path !== null && $path !== '/' && $path !== '\\' && $path !== '') {
            $publicPath .= DIRECTORY_SEPARATOR . trim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
        }

        return $publicPath;
    }
}

if (!function_exists('data_path')) {
    function data_path($path = null)
    {
        $dataPath = realpath(SharedContainer::getInstance()->get('config')->get('application.data_path', 'data'));

        if ($path !== null && $path !== '/' && $path !== '\\' && $path !== '') {
            $dataPath .= DIRECTORY_SEPARATOR . trim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
        }

        return $dataPath;
    }
}

if (!function_exists('module_path')) {
    /**
     * @param $module
     * @param null $path
     * @return string
     * @throws CoreException
     */
    function module_path($module, $path = null)
    {
        $modules = SharedContainer::getInstance()->get('app')->getModules();

        if (!isset($modules[$module])) {
            throw new CoreException(sprintf('[' . __FUNCTION__ . '] Invalid module "%s"', $module));
        }

        $modulePath = $modules[$module]->getDir();

        if ($path !== null && $path !== '/' && $path !== '\\' && $path !== '') {
            $modulePath .= DIRECTORY_SEPARATOR . trim($path, '/\\');
        }

        return $modulePath;
    }
}

if (!function_exists('base_url')) {
    function base_url($path = null)
    {
        $uri = SharedContainer::getInstance()->get('request')->getUri();
        $baseUrl = '';

        if (method_exists($uri, 'getBasePath')) {
            $baseUrl = $uri->getBasePath();
            if (preg_match('~\.php$~', $baseUrl)) {
                $baseUrl = rtrim(str_replace(DIRECTORY_SEPARATOR, '/', dirname($baseUrl)), '/');
            }
        }

        if ($path !== null && $path !== '/' && $path !== '') {
            return $baseUrl . '/' . trim($path, '/');
        }

        return $baseUrl;
    }
}

if (!function_exists('server_url')) {
    function server_url($path = null)
    {
        $uri = SharedContainer::getInstance()->get('request')->getUri();

        $host = $uri->getHost();
        $scheme = $uri->getScheme();
        $port = $uri->getPort();

        if ($port) {
            $host .= ':' . $port;
        }

        $serverUrl = $scheme . '://' . $host;

        if ($path !== null && $path !== '/' && $path !== '') {
            $serverUrl .= '/' . trim($path, '/');
        }

        return $serverUrl;
    }
}

if (!function_exists('config')) {
    function config($key = null, $value = null)
    {
        return SharedContainer::getInstance()->get('config')->get($key, $value);
    }
}

if (!function_exists('env')) {
    /**
     * @deprecated
     * @param null|string $env
     * @return boolean|string
     */
    function env($env = null)
    {
        return micro_env($env);
    }
}

if (!function_exists('micro_env')) {
    /**
     * @param null|string $env
     * @return boolean|string
     */
    function micro_env($env = null)
    {
        if (defined('APP_ENV')) {
            $_env = APP_ENV;
        } else {
            $_env = getenv('APP_ENV');
            if ($_env === false) {
                $_env = 'production';
            }
        }

        if ($env === null) {
            return $_env;
        }

        if (is_array($env)) {
            if (in_array($_env, $env)) {
                return true;
            }
        } else if ($_env === $env) {
            return true;
        }

        return false;
    }
}

if (!function_exists('is_debug')) {
    function is_debug()
    {
        return micro_env('development') || is_debug_cookie();
    }
}

if (!function_exists('is_debug_cookie')) {
    function is_debug_cookie()
    {
        return (isset($_COOKIE['_micro_debug']) &&  $_COOKIE['_micro_debug'] == 'icydebug');
    }
}

if (!function_exists('expr')) {
    /**
     * @param string $expression
     * @return \Micro\Database\Expr
     */
    function expr($expression)
    {
        return new Micro\Database\Expr($expression);
    }
}

if (!function_exists('route')) {
    /**
     * @param string $name
     * @param array $data
     * @param boolean $reset
     * @param boolean $queryStringAppend
     * @param boolean $queryStringAppendRouteOnly
     * @throws \Exception
     * @return string
     */
    function route($name = null, array $data = [], $reset = false, $queryStringAppend = true, $queryStringAppendRouteOnly = false)
    {
        return SharedContainer::getInstance()->get('router')->assemble($name, $data, $reset, $queryStringAppend, $queryStringAppendRouteOnly);
    }
}

if (!function_exists('route_reset')) {
    /**
     * @param string $name
     * @param array $data
     * @throws \Exception
     * @return string
     */
    function route_reset($name = null, array $data = [])
    {
        return SharedContainer::getInstance()->get('router')->assemble($name, $data, true, true, true);
    }
}

if (!function_exists('json')) {
    /**
     * @param mixed $body
     * @param int $code
     * @return \Micro\Http\Response\JsonResponse
     */
    function json($body, $code = 200)
    {
        return new JsonResponse($body, $code);
    }
}

if (!function_exists('redirect')) {
    /**
     * @param string $url
     * @param int $code
     * @return \Micro\Http\Response\RedirectResponse
     */
    function redirect($url, $code = 302)
    {
        return new RedirectResponse($url, $code);
    }
}

if (!function_exists('view')) {
    /**
     * @param string $template
     * @param array|null $data
     * @param array|null $paths
     * @return \Micro\View\View
     */
    function view($template, ?array $data = null, ?array $paths = null)
    {
        return new View($template, $data, $paths);
    }
}

if (!function_exists('render_view')) {
    /**
     *
     * @param string $template
     * @param string $module
     * @param array $data
     * @param array $addPaths [module=>[module_path(path1), module_path(path2)]]
     * @param boolean $renderParent
     * @return string
     */
    function render_view($template, $module = null, $data = [], $addPaths = [], $renderParent = false)
    {
        $view      = new View();
        $container = SharedContainer::getInstance();

        if ($module == null) {
            $request = $container->get('request');
            $module  = ucfirst(Utils::camelize($request->getAttribute('module')));
        }

        if ($module) {
            $view->addPath(module_path($module) . '/Resources/views', Utils::decamelize($module));
        }

        $view->addPath($container->get('config')->get('view.paths', []));

        if (!empty($addPaths)) {
            $view->addPath($addPaths);
        }

        if (!empty($data)) {
            $view->assign($data);
        }

        if ($renderParent) {
            return $view->setTemplate($template)->render();
        }

        return $view->render($template);
    }
}

if (!function_exists('identity')) {
    /**
     * @param boolean $force
     * @return \Micro\Auth\Identity
     */
    function identity($force = false)
    {
        return Auth::identity($force);
    }
}

if (!function_exists('flash')) {
    /**
     * @return \Micro\Helper\Flash
     */
    function flash()
    {
        return new Flash();
    }
}

if (!function_exists('escape')) {
    /**
     * @param string $var
     * @param string $functions
     * @return string
     */
    function escape($var, $functions = null)
    {
        static $flags;

        if (!isset($flags)) {
            $flags = ENT_QUOTES | (defined('ENT_SUBSTITUTE') ? ENT_SUBSTITUTE : 0);
        }

        if ($functions !== null) {
            foreach (explode('|', $functions) as $func) {
                if (is_callable($func)) {
                    $var = call_user_func($func, $var);
                }
            }
        }

        return htmlspecialchars($var ?? '', $flags, 'UTF-8');
    }
}

if (!function_exists('escaper')) {
    /**
     * @param string|null $type
     * @param string|null $string
     * @return \Micro\Escaper\Escaper|string
     * @throws Exception
     */
    function escaper($type = null, $string = null)
    {
        static $escaper;

        if ($escaper === null) {
            $escaper = new Escaper();
        }

        if ($type !== null && $string !== null) {
            $method = "escape" . ucfirst($type);
            if (method_exists($escaper, $method)) {
                return $escaper->$method($string);
            } else {
                throw new CoreException(sprintf('Escaper "%s" method not found', $method));
            }
        }

        return $escaper;
    }
}

if (!function_exists('is_allowed')) {
    /**
     * @param string $resource
     * @param string $role
     * @param string $privilege
     * @return boolean
     */
    function is_allowed($resource = null, $role = null, $privilege = true)
    {
        static $cache;

        $container = SharedContainer::getInstance();

        $acl = $container->get('acl');

        if ($acl === null) {
            return true;
        }

        if ($role === null) {

            $identity = Auth::identity();

            $role = 'guest';

            if ($identity instanceof RoleInterface) {
                try {
                    $role = $identity->getRoleId();
                } catch (Throwable $e) {
                    trigger_error($e->getMessage(), E_USER_WARNING);
                }
            }
        }

        if ($resource === null) {

            $route = $container->get('router')->getCurrentRoute();

            $resource = 'access';

            if ($route !== null) {

                $resource = $route->getHandler();

                if ($resource instanceof Closure) {
                    $resource = call_user_func($resource, $container->get('request'), $container->get('response'));
                }
            }
        }

        if (!is_scalar($resource)) {
            return true;
        }

        $key = $role . '_' . $resource . '_' . json_encode($privilege);

        if (isset($cache[$key])) {
            return $cache[$key];
        }

        return $cache[$key] = $acl->isAllowed($role, $resource, $privilege);
    }
}

if (!function_exists('forward')) {
    /**
     * @param string $resource
     * @param array $params
     * @param string $subRequest
     * @return Response
     */
    function forward($resource, array $params = [], $subRequest = false)
    {
        $container = SharedContainer::getInstance();

        $request = clone $container->get('request');
        if ($request instanceof Request) {
            $request->withBody(new TempStream());
            if (!empty($params)) {
                $request->withAttributes($params);
            }
        }

        $response = clone $container->get('response');
        if ($response instanceof Response) {
            $response->withBody(new TempStream());
        }

        return $container->get('resolver')->resolve($resource, $request, $response, $subRequest);
    }
}

if (!function_exists('widget')) {
    /**
     * @param string $resource
     * @param array $params
     * @return string
     */
    function widget($resource, array $params = [])
    {
        $body = forward($resource, $params, true)->getBody();
        $body->rewind();

        return $body->getContents();
    }
}

if (!function_exists('pagination')) {
    function pagination(Paginator $paginator, $partial, ?array $params = null, ?View $view = null)
    {
        $pages = ['pages' => $paginator->getPages()];

        if ($params !== null) {
            $pages = array_merge($pages, (array) $params);
        }

        if ($view === null) {
            return render_view($partial, null, $pages);
        }

        return $view->partial($partial, $pages);
    }
}

if (!function_exists('translate')) {
    function translate($key, $code = null)
    {
        static $cache = [];

        if (empty($key)) {
            return '';
        }

        $cacheId = $key . '_' . (string) $code;

        if (isset($cache[$cacheId])) {
            return $cache[$cacheId];
        }

        $container = SharedContainer::getInstance();

        if ($code === null && $container->has('language') && ($language = $container->get('language')) instanceof LanguageInterface) {
            $code = $language->getCode();
        }

        $value = ($code === null ? $key : $container->get('translator')->translate($key, $code));

        return $cache[$cacheId] = $value;
    }
}

if (!function_exists('translate_empty')) {
    function translate_empty($key, $code = null)
    {
        $tkey = translate($key, $code);

        if ($key === $tkey) { // not translation
            return '';
        }

        return $tkey;
    }
}

if (!function_exists('str_slug')) {
    function str_slug($string, $sep = '-', $fromLanguage = null, $truncate = 255)
    {
        static $cache = [];

        $origin = $string;

        if (isset($cache[$origin])) {
            return $cache[$origin];
        }

        if ($truncate !== null) {
            $string = truncate($string, $truncate, '');
        }

        return $cache[$origin] = Transliterate::slug($string, $sep, $fromLanguage);
    }
}

if (!function_exists('truncate')) {
    function truncate($string, $truncate = 100, $suffix = ' ...')
    {
        if ($truncate !== null && mb_strlen($string, 'UTF-8') > $truncate) {
            $string = mb_substr($string, 0, $truncate, 'UTF-8') . $suffix;
        }

        return $string;
    }
}

if (!function_exists('to_date')) {
    function to_date($value, $format = 'Y-m-d')
    {
        if (empty($value)) {
            return null;
        }

        $value = new DateTime($value);

        return $value->format($format);
    }
}

if (!function_exists('build_options')) {
    function build_options($optionsInput, $value = 0, $emptyOption = null, $emptyOptionValue = "", $translateValue = \false, $strict = \false)
    {
        return Utils::buildOptions($optionsInput, $value, $emptyOption, $emptyOptionValue, $translateValue, $strict);
    }
}

if (!function_exists('asset')) {
    /**
     * @param array|string|null $name
     * @return \Micro\Assets\Bundle
     */
    function asset($name = null)
    {
        $default = 'default';
        $files   = [];

        if ($name === null) {
            $name = $default;
        } elseif (is_array($name)) {
            $files = $name;
            $name  = $default;
        }

        $bundle = SharedContainer::getInstance()->get('asset')->create($name);

        if (!empty($files)) {
            $bundle->add($files);
        }

        return $bundle;
    }
}

if (!function_exists('raise')) {
    /**
     * @param string $message
     * @param number $code
     * @throws Exception
     */
    function raise($message, $code = 700)
    {
        throw new Exception($message, $code);
    }
}

if (!function_exists('trigger')) {
    /**
     * @param string $event
     * @param array $params
     * @return mixed
     */
    function trigger($event, array $params = [])
    {
        return app('event')->trigger($event, $params);
    }
}

if (!function_exists('cache_data')) {
    /**
     * @param string $name
     * @param string $id
     * @param Closure $callback
     * @param array $tags
     * @param string $lifetime
     * @param boolean $useLock
     * @param boolean $asArray
     * @return false|mixed
     */
    function cache_data($name, $id, Closure $callback, array $tags = [], $lifetime = false, $useLock = false, $asArray = true)
    {
        $cache = cache($name);

        if (!$cache instanceof Cache\Core) {
            trigger_error("Cache adapter {$name} not found", E_USER_WARNING);
            $data = $callback();
            if ($asArray && !is_array($data)) {
                $data = [];
            }
            return $data;
        }

        $id = Cache\Cache::normalizeKey($id);

        if (($data = $cache->load($id)) === false) {

            if ($useLock) {
                $lock = new Lock(data_path(sprintf('lock/%s.lock', $id)));
                $lock->acquire();
                if (($data = $cache->load($id)) === false) {
                    $data = $callback();
                    if ($asArray && !is_array($data)) {
                        $data = [];
                    }
                    $cache->save($data, $id, $tags, $lifetime);
                }
                $lock->release();
            } else {
                $data = $callback();
                if ($asArray && !is_array($data)) {
                    $data = [];
                }
                $cache->save($data, $id, $tags, $lifetime);
            }
        }

        return $data;
    }
}

if (!function_exists('cache_data_test')) {
    /**
     * @param string $name
     * @param string $id
     * @param Closure $callback
     * @param array $tags
     * @param string $lifetime
     * @param boolean $useLock
     * @param boolean $asArray
     * @return mixed
     */
    function cache_data_test($name, $id, Closure $callback, array $tags = [], $lifetime = false, $useLock = false, $asArray = true)
    {
        $cache = cache($name);

        $getData = function () use ($callback, $asArray) {
            $data = $callback();
            if ($asArray && !is_array($data)) {
                $data = [];
            }
            return $data;
        };

        if (!$cache instanceof Cache\Core) {
            trigger_error("Cache adapter {$name} not found", E_USER_WARNING);
            return $getData();
        }

        $id = Cache\Cache::normalizeKey($id);

        if (($data = $cache->load($id)) === false) {
            try {
                if ($useLock) {
                    $lock = new Lock(data_path(sprintf('lock/%s.lock', $id)));
                    $lock->acquire();
                }
                if ($useLock === false || ($data = $cache->load($id)) === false) {
                    $data = $getData();
                    $cache->save($data, $id, $tags, $lifetime);
                }
            } catch (\Throwable $e) {
                trigger_error($e->getMessage(), E_USER_WARNING);
            }
            if ($useLock && isset($lock)) {
                $lock->release();
            }
        }

        return $data;
    }
}

if (!function_exists('cache')) {
    /**
     * @param string $name
     * @return Cache\Core
     */
    function cache($name = null)
    {
        if ($name == null) {
            $name = config('cache.default');
        }

        return Cache\Cache::load($name);
    }
}

if (!function_exists('cache_json_data')) {
    /**
     * @deprecated
     * @param string $id
     * @param Closure $callback
     * @param array $tags
     * @param string $lifetime
     * @param boolean $useLock
     * @return false|mixed
     */
    function cache_json_data($id, Closure $callback, array $tags = [], $lifetime = false, $useLock = false)
    {
        return cache_data('json', $id, $callback, $tags, $lifetime, $useLock);
    }
}

if (!function_exists('cache_html_data')) {
    /**
     * @param string $id
     * @param Closure $callback
     * @param array $tags
     * @param string $lifetime
     * @return false|mixed
     */
    function cache_html_data($id, Closure $callback, array $tags = [], $lifetime = false, $useLock = false)
    {
        $name = 'html';
        $cache = cache($name);

        if (!$cache instanceof Cache\Core) {
            trigger_error("Cache adapter {$name} not found", E_USER_WARNING);
            if (!is_string($html = $callback())) {
                $html = '';
            }
            return $html;
        }

        $id = Cache\Cache::normalizeKey($id);

        if (($html = $cache->load($id)) === false) {

            if ($useLock) {
                $lock = new Lock(data_path(sprintf('lock/%s.lock',$id)), 30, Lock::EXCLUSIVE, Lock::NON_BLOCKING, \true);
                $lock->acquire();
            }

            if (($html = $cache->load($id)) === false) {

                $html = $callback();

                if (!is_string($html)) {
                    $html = '';
                }

                $cache->save($html, $id, $tags, $lifetime);
            }

            if ($useLock && $lock) {
                $lock->release();
            }
        }

        return $html;
    }
}

if (!function_exists('job')) {
    function job($job, $payload, array $params = [], ?Micro\Job\Queue\QueueInterface $queue = null)
    {
        if ($queue === null) {
            $queue = new Micro\Job\Queue\TableQueue();
        }

        $queue->push($job, $payload, $params);
    }
}

if (!function_exists('job_later')) {
    function job_later($seconds, $job, $payload, array $params = [], ?Micro\Job\Queue\QueueInterface $queue = null)
    {
        if ($queue === null) {
            $queue = new Micro\Job\Queue\TableQueue();
        }

        $queue->pushLater($seconds, $job, $payload, $params);
    }
}

if (!function_exists('user_ip')) {
    function user_ip()
    {
        foreach (['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'] as $key) {
            if (array_key_exists($key, $_SERVER) === true){
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip); // just to be safe
                    if (filter_var($ip, FILTER_VALIDATE_IP) !== false){
                        return $ip;
                    }
                }
            }
        }
        return null;
    }
}

if (!function_exists('session')) {
    /**
     * @param string $namespace
     * @param bool $singleInstance
     * @return SessionNamespace
     */
    function session($namespace = 'Default', $singleInstance = false)
    {
        return new SessionNamespace($namespace, $singleInstance);
    }
}

if (!function_exists('is_image')) {
    function is_image($path)
    {
        if (!is_file($path)) {
            return false;
        }

        $imagesize = @getimagesize($path);

        if ($imagesize && isset($imagesize['bits'])) {
            return true;
        }

        return false;
    }
}

if (!function_exists('compress')) {
    function compress($html)
    {
        return Utils::compress($html);
    }
}

if (!function_exists('mb_ucfirst')) {
    function mb_ucfirst($string)
    {
        return mb_strtoupper(mb_substr($string, 0, 1)) . mb_substr($string, 1);
    }
}