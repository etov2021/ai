<?php

namespace Micro\Router;

use Micro\Exception\Exception as CoreException;
use Micro\Application\MiddlewareAwareTrait;
use Micro\Container\ContainerAwareInterface;
use Micro\Container\ContainerAwareTrait;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class Route implements ContainerAwareInterface
{
    use ContainerAwareTrait, MiddlewareAwareTrait;

    const CONDITION = '[^/]+';

    const REGEX = '~
        ([^{}\[\]]+)|
        (\[)?
        ([^{}\[\]]+)?
        {([^}]+)}
        ([^{}\[\]]+)?
        (\])?
    ~xiu';

    /**
     * @var string
     */
    protected $pattern;

    /**
     * @var array
     */
    protected $conditions = [];

    /**
     * @var string
     */
    protected $compiled;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var \Closure|string
     */
    protected $handler;

    /**
     * @var array
     */
    protected $defaults = [];

    /**
     * @var array
     */
    protected $params = [];

    /**
     * @var boolean
     */
    protected $middlewareAreAdded = \false;

    /**
     * @var \Closure
     */
    protected $onMatch;

    /**
     * @var \Closure
     */
    protected $onAssemble;

    /**
     * Allowed methods
     * @var array
     */
    protected $methods = [];

    /**
     * @var string
     */
    protected $description;

    /**
     *
     * @var array
     */
    protected $options = [];

    /**
     * @var array
     */
    protected $optionals = [];

    /**
     * @param string $name
     * @param string $pattern
     * @param \Closure|string $handler
     * @param array $methods
     */
    public function __construct($name, $pattern, $handler, array $methods = [])
    {
        $this->name = $name;
        $this->pattern = $pattern;

        if ($handler instanceof \Closure) {
            $handler = $handler->bindTo($this);
        }

        $this->handler = $handler;

        if (!empty($methods)) {
            $this->setMethods($methods);
        }
    }

    /**
     * @param string $requestUri
     * @param string $method
     * @return boolean
     */
    public function match($requestUri, $method)
    {
        $flag = \false;

        if (!$this->isAllowed($method)) {
            return $flag;
        }

        if (\preg_match('~^' . $this->compile() . '$~iu', $requestUri, $matches)) {

            foreach ($this->params as $k => $v) {
                if (isset($matches[$k]) && $matches[$k] !== '' && $matches[$k] !== null) {
                    $this->params[$k] = $matches[$k];
                }
            }

            $flag = \true;

            if ($this->onMatch instanceof \Closure) {
                $flag = (bool) \call_user_func($this->onMatch);
            }
        }

        return $flag;
    }

    /**
     * Compile route pattern to regex
     * @return string
     */
    public function compile()
    {
        if ($this->compiled === \null) {

            $compiled = '';

            $pattern = $this->pattern;

            if (\preg_match_all(static::REGEX, $pattern, $matches)) {

                foreach ($matches[4] as $k => $param) {

                    if (empty($param)) {
                        $compiled .= $matches[1][$k];
                        continue;
                    }

                    // default regex
                    $regex = static::CONDITION;

                    if (isset($this->conditions[$param])) {
                        $regex = $this->conditions[$param];
                    }

                    $parsedRegex = \preg_quote($matches[3][$k]) . '(?P<' . $param . '>' . $regex . ')' . \preg_quote($matches[5][$k]);

                    if ($matches[2][$k] === '[') {
                        $parsedRegex = '(' . $parsedRegex . ')?';
                        $this->optionals[$param] = $parsedRegex;
                    }

                    $compiled .= $parsedRegex;

                    $this->params[$param] = isset($this->defaults[$param])
                                            ? $this->defaults[$param]
                                            : \null;
                }
            }

            $this->compiled = $compiled;
        }

        return $this->compiled;
    }

    /**
     * @param string $compiled
     * @return Route
     */
    public function setCompiled($compiled)
    {
        $this->compiled = $compiled;

        return $this;
    }

    /**
     * @param array $data
     * @param bool $reset
     * @throws \Exception
     * @return string
     */
    public function assemble(array &$data = [], $reset = \false)
    {
        $url = '';

        $error = false;

        if (\preg_match_all(static::REGEX, $this->pattern, $matches)) {

            $data += ($reset ? [] : $this->params) + $this->defaults;

            if ($this->onAssemble instanceof \Closure) {
                $data = \call_user_func($this->onAssemble, $data);
            }

            foreach ($matches[4] as $k => $v) {
                if (empty($v)) { // literal
                    $url .= $matches[1][$k];
                    unset($data[$v]);
                    continue;
                }
                if (isset($data[$v])/*  || array_key_exists($v, $data) */) { // exists in user params
                    $matches[4][$k] = $data[$v];
                    unset($data[$v]);
                } else {
                    if ($matches[2][$k] === '[') { // optional parameter. remove optionals if not exists
                        unset($matches[2][$k]);
                        unset($matches[3][$k]);
                        unset($matches[4][$k]);
                        unset($matches[5][$k]);
                    } else { // required parameter. mark as error
                        $error = true;
                        $matches[4][$k] = '{' . $v . '}';
                    }
                }
                // build url
                if (isset($matches[4][$k]) && $matches[4][$k] !== '') {
                    if (isset($matches[3][$k]) && $matches[3][$k] !== '') {
                        $url .= $matches[3][$k];
                    }
                    $url .= $matches[4][$k];
                }
                if (isset($matches[5][$k]) && $matches[5][$k] !== '') {
                    $url .= $matches[5][$k];
                }
            }
        }

        if ($error) { // check something wrong
            throw new \InvalidArgumentException(sprintf('Too few arguments? "%s"!', $url), 500);
        }

        return '/' . ltrim($url, '/');
    }

    /**
     * @return \Closure|string
     */
    public function getHandler()
    {
        return $this->handler;
    }

    /**
     * @param string $key
     * @param string $value
     * @return Route
     */
    public function addCondition($key, $value)
    {
        $this->conditions[$key] = $value;

        return $this;
    }

    /**
     * @param array $conditions
     * @return Route
     */
    public function setConditions(array $conditions)
    {
        $this->conditions = $conditions;

        return $this;
    }

    /**
     * @return array
     */
    public function getConditions()
    {
        return $this->conditions;
    }

    /**
     * @param string $key
     * @param string $value
     * @return Route
     */
    public function addDefault($key, $value)
    {
        $this->defaults[$key] = $value;

        return $this;
    }

    /**
     * @param array $defaults
     * @return Route
     */
    public function setDefaults(array $defaults)
    {
        $this->defaults = $defaults;

        return $this;
    }

    /**
     * @return array
     */
    public function getDefaults()
    {
        return $this->defaults;
    }

    /**
     * @param string $pattern
     * @return Route
     */
    public function setPattern($pattern)
    {
        $this->pattern = $pattern;

        return $this;
    }

    /**
     * @return string
     */
    public function getPattern()
    {
        return $this->pattern;
    }

    /**
     * @return array
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * @param string $key
     * @param string $default
     * @return string
     */
    public function getParam($key, $default = \null)
    {
        return isset($this->params[$key]) ? $this->params[$key] : $default;
    }

    /**
     * @param array $params
     * @return Route
     */
    public function setParams(array $params)
    {
        $this->params = $params;

        return $this;
    }

    /**
     * @param string $key
     * @param string $value
     * @return Route
     */
    public function setParam($key, $value)
    {
        $this->params[$key] = $value;

        return $this;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string
     * @return Route
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @param \Closure $callback
     * @return Route
     */
    public function setOnMatch(?\Closure $callback = \null)
    {
        $this->onMatch = $callback ? $callback->bindTo($this) : \null;

        return $this;
    }

    /**
     * @param \Closure $callback
     * @return Route
     */
    public function setOnAssemble(?\Closure $callback = \null)
    {
        $this->onAssemble = $callback ? $callback->bindTo($this) : \null;

        return $this;
    }

    /**
     * @param array $methods
     * @return Route
     */
    public function setMethods(array $methods)
    {
        $this->methods = $methods;

        return $this;
    }

    /**
     * Check if method is allowed
     * @param string $method
     * @return boolean
     */
    public function isAllowed($method)
    {
        return empty($this->methods) || \in_array($method, $this->methods);
    }

    /**
     * @param string $description
     * @return Route
     */
    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return empty($this->description) ? 'Route ' . strtolower($this->name) : $this->description;
    }

    /**
     *
     * @param array $options
     * @return \Micro\Router\Route
     */
    public function setOptions($options = [])
    {
        $this->options = $options;

        return $this;
    }

    /**
     *
     * @return array
     */
    public function getOptions($key = null)
    {
        if($key) {
            if(isset($this->options[$key])) {
                return $this->options[$key];
            }
        }
        return $this->options;
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return mixed
     * @throws CoreException
     */
    public function __invoke(Request $request, Response $response)
    {
        $handler = $this->getHandler();

        if ($handler instanceof \Closure) {
            $handler = \call_user_func($handler, $request, $response);
        }

        // short circuit
        if ($handler instanceof Response) {
            return $handler;
        }

        $resolver = $this->container->get('resolver');

        if (\is_object($resolver) && \method_exists($resolver, 'resolve')) {
            return $resolver->resolve($handler, $request, $response);
        }

        throw new CoreException(\sprintf('Resolver [%s] does not have method "resolve"', \is_object($resolver) ? \get_class($resolver) : \gettype($resolver)), 500);
    }

    /**
     * @return array
     */
    public function toArray()
    {
        $data = [];

        foreach ($this as $k => $v) {
            if (is_object($v)) {
                $v = get_class($v);
            }
            $data[$k] = is_scalar($v) ? $v : json_encode($v);
        }

        return $data;
    }

    /**
     * @return array
     */
    public function getOptionals()
    {
        return $this->optionals;
    }
}