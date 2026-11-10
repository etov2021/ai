<?php

namespace Translations\Multilingual;

use Micro\Container\ContainerInterface;
use Translations\Model\Languages;
use Translations\Model\Entity\Language;
use Micro\Application\Controller\Crud;
use Micro\Cache;
use Micro\Http\Response\RedirectResponse;

class Provider
{
    const DEFAULT_LANG = 'bg';

    /**
     * @var ContainerInterface
     */
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function register()
    {
        $event = $this->container->get('event');
        $event->attach('Application.Start', [$this, 'onApplicationStart']);
        $event->attach('Route.End', [$this, 'onRouteEnd']);
    }

    public function onApplicationStart()
    {
        $router  = $this->container->get('router');
        $config  = $this->container->get('config');
        $cache   = $this->container->get('cache');

        $lang = $config->get('language.default', static::DEFAULT_LANG);

        if (!$cache instanceof Cache\Core || (($languages = $cache->load('Languages')) === \false)) {

            $languages = [];

            foreach ((new Languages())->addWhere('active', 1)->getItems() as $item) {
                $languages[$item->getCode()] = $item;
            }

            if (!isset($languages[$lang])) {
                $languages[$lang] = new Language(0, $lang);
            }

            if ($cache) {
                $cache->save($languages, 'Languages', ['Translations_Model_Languages']);
            }
        }

        // SET languages
        $this->container->set('languages', $languages);

        // SET default language
        $this->container->set('default_language', $languages[$lang]);

        // SET UI language
        $this->container->set('language', function ($container) use ($languages) {

            $lang = $container->get('request')->getParam('lang');

            if ($lang !== null && isset($languages[$lang])) {
                return $languages[$lang];
            }

            return $container->get('default_language');
        });

        // SET current language
        $this->container->set('current_language', function ($container) use ($languages) {

            $lang = $container->get('request')->getParam(Crud::CURRENT_LANG_KEY);

            if ($lang !== null && isset($languages[$lang])) {
                return $languages[$lang];
            }

            return $container->get('language');

        });

        $validLanguages = \implode('|', \array_keys($languages));

        foreach ($router->getRoutes() as $route) {

            $pattern = $route->getPattern();

            if ($pattern === '/') {
                $route->setPattern('/[{lang}]');
            } else {
                $route->setPattern('[/{lang}]' . $pattern);
            }

            if ($validLanguages) {
                $route->addCondition('lang', '(?:' . $validLanguages . ')');
            }

            $route->addDefault('lang', \null);
        }
    }

    public function onRouteEnd()
    {
        $router  = $this->container->get('router');
        $request = $this->container->get('request');
        $config  = $this->container->get('config');

        if ($config->get('language.present_always')) {
            $router->setGlobalParam('lang', $request->getAttribute('lang', $config->get('language.default', self::DEFAULT_LANG)));
        } else {
            $lang = $request->getAttribute('lang');
            if ($lang && $this->container->has('domain') && $this->container->has('language')) {
                $domain = $this->container->get('domain');
                $language = $this->container->get('language');
                if ($domain && $language && $domain->getDefaultLanguage() == $language->getId()) {
                    return new RedirectResponse(route(null, ['lang' => null]), 301);
                }
            }
            $router->setGlobalParam('lang', $lang);
        }
    }
}