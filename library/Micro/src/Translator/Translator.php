<?php

namespace Micro\Translator;

use Micro\Translator\Adapter\AdapterAbstract;
use Micro\Container\SharedContainer;

class Translator
{
    /**
     * @var null|false|AdapterAbstract
     */
    protected $adapter;
    protected $options = [];

    public function __construct(?AdapterAbstract $adapter = \null)
    {
        if ($adapter !== \null) {
            $this->setAdapter($adapter);
        }

        $options = SharedContainer::getInstance()->get('config')->get('translator.options');

        $this->setOptions($options);
    }

    public function getAdapter()
    {
        if ($this->adapter === \null) {
            $adapter = SharedContainer::getInstance()->get('config')->get('translator.adapter');
            if ($adapter !== null) {
                if (!\class_exists($adapter)) {
                    $adapter = 'Micro\Translator\Adapter\\' . \ucfirst($adapter);
                    if (!\class_exists($adapter)) {
                        \trigger_error('Invalid translator "' . $adapter . '"', \E_USER_WARNING);
                        return $this->adapter = false;
                    }
                }
                $this->adapter = new $adapter;
                if (!$this->adapter instanceof AdapterAbstract) {
                    \trigger_error('Translator "' . \get_class($adapter) . '" must be instanceof ' . AdapterAbstract::class, \E_USER_WARNING);
                    return $this->adapter = \false;
                }
            } else {
                $this->adapter = \false;
            }
        }

        return $this->adapter;
    }

    public function setAdapter(AdapterAbstract $adapter)
    {
        $this->adapter = $adapter;

        return $this;
    }

    public function setOptions($options)
    {
        $this->options = $options;

        return $this;
    }

    public function getOptions()
    {
        return $this->options;
    }

    /**
     * @param string $key
     * @param string $code
     * @return mixed
     */
    public function translate($key, $code)
    {
        if (($adapter = $this->getAdapter()) !== \false) {
            if (isset($this->options['logTranslations']) && $this->options['logTranslations'] == true) {
                self::logTranslation($key);
            }
            return $adapter->translate($key, $code);
        }

        return $key;
    }

    /**
     * @param string $key
     * @param string $code
     * @return string[]
     */
    public static function logTranslation($key, $code = null)
    {
        $pool = [];
        $key = trim($key);

        $filePath = data_path('log/translations.txt');

        if (file_exists($filePath)) {

            $file_handle = fopen($filePath, "r");

            while (!feof($file_handle)) {
                $line = fgets($file_handle);
                $line = trim($line);
                $pool[$line] = $line;
            }

            fclose($file_handle);

            if (!in_array($key, $pool)) {
                file_put_contents($filePath, $key . "\n", FILE_APPEND);
                $pool[$key] = $key;
            }
        }

        return $pool;
    }
}