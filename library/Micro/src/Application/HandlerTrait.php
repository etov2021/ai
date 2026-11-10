<?php

namespace Micro\Application;

use Micro\Http\Request;
use Micro\Http\Response;
use Micro\Container\ContainerInterface;

trait HandlerTrait
{
    /**
     * @var Request
     */
    protected $request;

    /**
     * @var Response
     */
    protected $response;

    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @param Request $request
     * @param Response $response
     * @param ContainerInterface $container
     */
    public function __construct(
        Request $request,
        Response $response,
        ContainerInterface $container
    ) {
        $this->request = $request;
        $this->response = $response;
        $this->container = $container;
    }
}