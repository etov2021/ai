<?php

namespace Micro\Transliterate;

use Micro\Exception\Exception as CoreException;
use Micro\Container\SharedContainer;
use Micro\Translator\Language\LanguageInterface;

class Transliterate
{
    public static function convert($text, $fromLanguage, $toLanguage = 'en')
    {
        $fromInstance = \null;
        $toInstance   = \null;

        if ($fromLanguage !== 'en') {
            $class = 'Micro\\Transliterate\\' . \ucfirst($fromLanguage);
            if (!\class_exists($class, \true)) {
                throw new CoreException("{$class} not found");
            }
            $fromInstance = new $class;
        }

        if ($toLanguage !== 'en') {
            $class = 'Micro\\Transliterate\\' . \ucfirst($toLanguage);
            if (!\class_exists($class, \true)) {
                throw new CoreException("{$class} not found");
            }
            $toInstance = new $class;
        }

        if ($fromInstance) {
            $text = $fromInstance->convert($text, \false);
        }

        if ($toInstance) {
            $text = $toInstance->convert($text, \true);
        }

        return $text;
    }

    public static function slug($string, $sep = '-', $fromLanguage = \null)
    {
        static $nullLanguage;

        if ($fromLanguage === \null) {

            if ($nullLanguage === null) {
                $container = SharedContainer::getInstance();
                if ($container->has('language') && ($language = $container->get('language')) instanceof LanguageInterface) {
                    $nullLanguage = $language->getCode();
                }
            }

            $fromLanguage = $nullLanguage;
        }

        if ($fromLanguage) {
            try {
                $string = static::convert($string, $fromLanguage);
            } catch (\Throwable $e) {
                // Do nothing...
            }
        }

        $string = \preg_replace('~[^a-z0-9]+~i', $sep, $string);

        if (empty($string) || $string === $sep) {
            return $sep;
        }

        return \strtolower(\trim($string, ' ' . $sep));
    }
}