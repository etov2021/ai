<?php

namespace Domains\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Domains\Model\Domains;
use Micro\Container\SharedContainer;
use Micro\Cache\Cache;
use Micro\Cache\Core as CacheCore;
use Domains\Model\Entity\Domain;

class DetectDomain
{
    const DEFAULT_DOMAIN = 'localhost';

    public function __invoke(Request $request, Response $response, $next)
    {
        if (is_debug()) {
            $cookieKey = preg_match('~^admin~', $request->getUri()->getPath()) ? 'admin_domain' : 'domain';
            $domainName = isset($_COOKIE[$cookieKey]) ? $_COOKIE[$cookieKey] : $this->detectDomain($request);
        } else {
            $domainName = $this->detectDomain($request);
        }

        $domain = $this->getDomain($domainName);

        if ($domain === null) {
            $domain = $this->getDomain(config('site.default_domain', static::DEFAULT_DOMAIN));
            if ($domain === null) {
                throw new \Exception('Invalid domain');
            }
        }

        $container = SharedContainer::getInstance();

        if ($domain instanceof Domain) {

            $languages = $container->get('languages');

            foreach ($languages as $language) {

                if ($language->getId() == $domain->getDefaultLanguage()) {

                    $container->set('default_language', $language);

                    $config = $container->get('config');

                    unset($config['language.default']);

                    $config->load(['language' => ['default' => $language->getCode()]]);

                    break;
                }
            }
        }

        define('DOMAIN', ($domain ? $domain->getId() : 0));

        $container->set('domain', $domain);

        return $next($request, $response);
    }

    protected function detectDomain(Request $request)
    {
        $host = $request->getUri()->getHost();

        $host = str_replace('www.', '', $host);

        return $host;
    }

    protected function getDomain($name)
    {
        $container = SharedContainer::getInstance();
        $cache     = $container->get('cache');
        $cacheId   = Cache::normalizeKey('CurrentDomain_' . $name);

        if (!$cache instanceof CacheCore || (($domain = $cache->load($cacheId)) === false)) {

            $domains = (new Domains())->getItems();
            $domain = null;

            foreach($domains as $domainTmp) {
                $addresses = array_filter(array_map('trim', explode("\n", $domainTmp->getAddresses())));
                if (in_array($name, $addresses)) {
                    $domain = $domainTmp;
                    break;
                }
            }

            if ($cache) {
                $cache->save($domain, $cacheId, ['Domains_Model_Domains']);
            }
        }

        return $domain;
    }
}