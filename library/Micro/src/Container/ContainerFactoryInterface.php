<?php

namespace Micro\Container;

interface ContainerFactoryInterface
{
    /**
     * @param ContainerInterface $container
     * @param string $service
     */
    public function create(ContainerInterface $container, $service);
}