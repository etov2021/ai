<?php

namespace App\Middleware\DevTools;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Micro\View\View;
use Micro\Event\Message;
use Micro\Http\Response\HtmlResponse;
use Micro\Database\Adapter\AdapterAbstract;
use Micro\Http\TempStream;
use Micro\Database\Profiler;

class DevTools
{
    /**
     * @var View
     */
    protected $view;

    public function __invoke(Request $request, Response $response, $next)
    {
        if (!is_debug()) {
            return $next($request, $response);
        }

        $response = $this->before($request, $response);

        $error = \null;

        try {
            $response = $next($request, $response);
        } catch (\Exception $e) {
            $error = $e;
        }

        $response = $this->after($request, $response);

        if ($error instanceof \Exception) {
            throw $error;
        }

        return $response;
    }

    public function before(Request $request, Response $response)
    {
        if (\config('micro_debug.handlers.dev_tools')) {

            $view = $this->view = new View('debug');
            $view->addPath(__DIR__ . '/Resources/views');

            \app('event')->attach('Render.Start', function (Message $message) use ($view) {
                $message->getParam('view')->section('styles', (string) $view->partial('css'));
            });
        }

        if (\config('micro_debug.handlers.fire_php') && ($db = \app('db')) instanceof AdapterAbstract) {
            $profiler = $db->getProfiler();
            if ($profiler instanceof Profiler) {
                $profiler->setEnabled(\true);
            } else {
                $db->setProfiler(\true);
            }
        }

        return $response;
    }

    protected function firePHPDatabase(FirePHP\FirePHP $firephp, AdapterAbstract $db, $total, $labelPrefix = 'DB')
    {
        $profiler = $db->getProfiler();

        if ($profiler->getEnabled()) {

            $totalTime    = $profiler->getTotalElapsedSecs();
            $queryCount   = $profiler->getTotalNumQueries();
            $longestTime  = 0;
            $longestQuery = \null;

            $label = 'Executed ' . $queryCount . ' queries in ' . sprintf('%.6f', $totalTime) . ' seconds. (' . ($total ? \round(($totalTime / $total) * 100, 2) : 0) . '%)';
            $table = [];
            $table[] = ['Time', 'Event', 'Parameters'];

            if ($profiler->getQueryProfiles()) {
                foreach ($profiler->getQueryProfiles() as $k => $query) {
                    if ($query->getQuery() !== 'connect' && $query->getElapsedSecs() > $longestTime) {
                        $longestTime  = $query->getElapsedSecs();
                        $longestQuery = $k;
                    }
                }
                foreach ($profiler->getQueryProfiles() as $k => $query) {
                    $table[] = [\sprintf('%.6f', $query->getElapsedSecs()) . ($k == $longestQuery ? ' !!!' : ''), $query->getQuery(), (($params = $query->getQueryParams()) ? json_encode($params) : \null)];
                    if (config('micro_debug.handlers.fire_php_explain') && preg_match('~^(?:SELECT|UPDATE|SELETE|INSERT)~ius', $query->getQuery())) {
                        try {
                            foreach ($db->fetchAll('EXPLAIN ' . $query->getQuery(), $query->getQueryParams()) as $row) {
                                unset($row['possible_keys']);
                                unset($row['key_len']);
                                unset($row['filtered']);
                                unset($row['ref']);
                                unset($row['partitions']);
                                $table[] = ['EXPLAIN', json_encode($row), ''];
                            }
                        } catch (\Exception $e) {
                            $table[] = ['EXPLAIN', 'ERROR: ' . $e->getMessage(), ''];
                        }
                        $table[] = ['', '', ''];
                    }
                }
            }

            $firephp->table($labelPrefix . ' - ' . $label, $table);
        }
    }

    public function after(Request $request, Response $response)
    {
        if (config('micro_debug.handlers.fire_php')) {

            $db = \app('db');
            $firephp = FirePHP\FirePHP::getInstance();
            $total = sprintf('%.6f', microtime(\true) - $_SERVER['REQUEST_TIME_FLOAT']);

            $firephp->info($total, 'Execution time');

            if ($db instanceof AdapterAbstract) {

                $this->firePHPDatabase($firephp, $db, $total);

                if (($slave = app('db_slave', true)) instanceof AdapterAbstract) {
                    $this->firePHPDatabase($firephp, $slave, $total, 'DB slave');
                }
            }
        }

        if (\config('micro_debug.handlers.dev_tools')) {

            if ($response instanceof HtmlResponse) {

                $body = $response->getBody();

                if ($body->isSeekable()) {
                    $body->rewind();
                }

                $b = $body->getContents();

                if (\strstr($b, '</body>') !== \false) {
                    $b = \explode('</body>', $b);
                    $b[0] .= \str_replace(["\n", "\t", "\r"], "", $this->view->render()) . '</body>';
                    $b = \implode('', $b);
                }

                $response->withBody(new TempStream($b));
            }
        }

        $queryParams = $request->getQueryParams();

        if (isset($queryParams['MICRO_PERFORMANCE']) && ($fileForCache = \config('micro_debug.handlers.performance')) !== \null) {

            $forStore = [];

            foreach (\MicroLoader::getFiles() as $class => $file) {
                if (\substr($class, 0, 6) === 'Micro\\') {
                    $forStore[$class] = $file;
                }
            }

            \file_put_contents($fileForCache, "<?php\nreturn " . \var_export($forStore, \true) . ";", \LOCK_EX);
        }

        return $response;
    }
}