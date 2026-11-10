<?php
namespace Micro\Commerce\Payment\Processor;

use Micro\Commerce\Payment\Exception;

class ProcessorAdapter
{
    public static function create($adapterName, $config = [])
    {
        $adapter = null;
        $class = __NAMESPACE__ . '\\Adapter\\' . ucfirst($adapterName);

        if(class_exists($class)) {
            $adapter = new $class($config);
        }

        if(!$adapter instanceof ProcessorInterface) {
            throw new Exception('Invalid payment adapter:  '. $adapterName);
        }

        return $adapter;
    }
}