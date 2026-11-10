<?php

namespace Micro\View;

use Micro\Exception\Exception as CoreException;
use Micro\Container\SharedContainer;
use Micro\Application\Application;

class View
{
    protected $__suffix = 'phtml';

    protected $__template;

    protected $__renderParent = \true;

    protected $__isParent = \false;

    protected $__module;

    protected $__paths = [];

    protected $__resolvedPaths = [];

    protected $__parent;

    protected $__currentSection;

    protected $__sections = [];

    protected $__data = [];

    protected static $__helpers = [];

    /**
     * @param string|null $template
     * @param array|null $data
     * @param array|null $paths
     */
    public function __construct($template = \null, ?array $data = \null, ?array $paths = \null)
    {
        $this->__template = $template;

        if ($data !== \null) {
            $this->assign($data);
        }

        if ($paths !== \null) {
            $this->addPath($paths);
        }
    }

    /**
     * @param string $path
     * @param string $namespace
     * @return View
     */
    public function prependPath($path, $namespace = \null)
    {
        $path = \rtrim($path, '/\\');

        if (empty($path)) {
            return $this;
        }

        $key = $namespace ?: $path;

        if (!isset($this->__paths[$key])) {
            $this->__paths = [$key => []] + $this->__paths;
        }

        array_unshift($this->__paths[$key], $path);

        return $this;
    }

    /**
     * @param string $path
     * @param string $namespace
     * @return View
     */
    public function addPath($path, $namespace = \null)
    {
        if (\is_array($path)) {
            foreach ($path as $k => $v) {
                $this->addPath($v, (\is_int($k) ? $namespace : $k));
            }
            return $this;
        }

        $path = \rtrim($path, '/\\');

        if (empty($path)) {
            return $this;
        }

        $key = $namespace ?: $path;

        if (!isset($this->__paths[$key])) {
            $this->__paths[$key] = [];
        }

        $this->__paths[$key][] = $path;

        return $this;
    }

    /**
     * @param string $template
     * @throws CoreException
     * @return string
     */
    public function render($template = \null)
    {
        $renderParent = $this->__renderParent;

        if ($template !== \null) {
            $renderParent = \false;
        } else {
            $template = $this->__template;
        }

        if (empty($template)) {
            throw new CoreException('Template is empty', 500);
        }

        $content = $this->evalFile(
            $this->templateExists($template, false)
        );

        if ($renderParent === \true && $this->__parent !== \null) {
            $this->__parent->setSections(\array_merge($this->getSections(), ['content' => $content]));
            $content = $this->__parent->render();
        }

        return $content;
    }

    public function templateExists($template, $silent = true)
    {
        $namespace = \null;

        if (\strstr($template, '::') !== \false) {
            $parts = \explode('::', $template);
            if (isset($parts[1])) {
                $namespace = $parts[0];
                $template = $parts[1];
            } else {
                $template = $parts[0];
            }
        }

        $file = \ltrim($template . '.' . $this->__suffix, '/\\');

        $paths = $namespace !== \null && isset($this->__paths[$namespace])
                 ? [$namespace => $this->__paths[$namespace]]
                 : $this->__paths;

        foreach ($paths as $namespacePaths) {

            foreach ($namespacePaths as $path) {

                $filePath = strtr($path . DIRECTORY_SEPARATOR . $file, ['\\' => DIRECTORY_SEPARATOR, '/' => DIRECTORY_SEPARATOR]);

                if (isset($this->__resolvedPaths[$filePath]) || \is_file($filePath)) {
                    $this->__resolvedPaths[$filePath] = $filePath;
                    return $filePath;
                }
            }
        }

        if ($silent) {
            return null;
        }

        throw new CoreException('Template "' . $file . '" not found in ' . \implode(', ', \array_map(function ($item) { return \implode(', ', $item); }, $paths)), 500);
    }

    /**
     * @param string $__path
     * @return string
     * @throws CoreException
     */
    public function evalFile($__path)
    {
        $__obLevel = \ob_get_level();

        \ob_start();

        \extract($this->__data);

        try {
            include $__path;
        } catch (\Throwable $e) {
            while (\ob_get_level() > $__obLevel) {
                \ob_end_clean();
            }
            throw $e;
        }

        return \ob_get_clean();
    }

    /**
     * @param array|string $data
     * @return View
     */
    public function setData(array $data)
    {
        $this->__data = [];

        $this->assign($data);

        return $this;
    }

    /**
     * @param string|array $key
     * @param mixed $value
     * @return View
     */
    public function assign($key, $value = \null)
    {
        if (\is_array($key)) {
            foreach ($key as $k => $v) {
                $this->assign($k, $v);
            }
            return $this;
        }

        if ($value === \null) {
            return $this;
        }

        if ($value instanceof ViewAwareInterface && !$value->getView()) {
            $value->setView($this);
        }

        $this->__data[$key] = $value;

        return $this;
    }

    /**
     * @param string $template
     * @return View
     */
    public function setTemplate($template)
    {
        $this->__template = $template;

        return $this;
    }

    /**
     * @return string
     */
    public function getTemplate()
    {
        return $this->__template;
    }

    /**
     * @param string $key
     * @return mixed
     */
    public function __get($key)
    {
        return isset($this->__data[$key]) ? $this->__data[$key] : \null;
    }

    /**
     * @param string $key
     * @param mixed $value
     */
    public function __set($key, $value)
    {
        $this->assign($key, $value);
    }

    /**
     * @param string $key
     * @return boolean
     */
    public function __isset($key)
    {
        return isset($this->__data[$key]);
    }

    /**
     * @param string $key
     */
    public function __unset($key)
    {
        if (isset($this->__data[$key])) {
            unset($this->__data[$key]);
        }
    }

    /**
     * @param string $template
     * @param array $data
     * @return string
     */
    public function partial($template, array $data = [])
    {
        $view = clone $this;

        $view->setSections([]);

        $view->setData($data);

        $view->setParent(\null);

        $view->setRenderParent(\false);

        $view->setTemplate($template);

        $view->assign('masterView', $this);

        return $view->render();
    }

    /**
     * @param string $template
     * @param array $data
     */
    public function extend($template, array $data = [])
    {
        $view = clone $this;

        $view->setRenderParent(\true);

        $view->setParent(\null);

        $view->assign($data);

        $view->setTemplate($template);

        $view->setIsParent(\true);

        $this->setParent($view);

        return $this;
    }

    /**
     * @param View $parent
     * @return View
     */
    public function setParent(?View $parent = \null)
    {
        $this->__parent = $parent;

        return $this;
    }

    /**
     * @param boolean $flag
     * @return View
     */
    public function setIsParent($flag)
    {
        $this->__isParent = (bool) $flag;

        return $this;
    }

    /**
     * @return boolean
     */
    public function isParent()
    {
        return $this->__isParent;
    }

    /**
     * @param string $section
     * @throws CoreException
     */
    public function start($section)
    {
        if ($this->__currentSection !== \null) {
            throw new CoreException('There is current started section', 500);
        }

        $this->__currentSection = $section;

        \ob_start();
    }

    /**
     * @throws CoreException
     * @return View
     */
    public function stop()
    {
        if ($this->__currentSection === \null) {
            throw new CoreException('There is not current started section', 500);
        }

        $section = $this->__currentSection;

        $this->__currentSection = \null;

        return $this->section($section, \ob_get_clean());
    }

    /**
     * @param string $section
     * @param string $content
     * @param boolean $clear
     * @return View
     */
    public function section($section, $content, $clear = false)
    {
        if (!isset($this->__sections[$section]) || $clear) {
            $this->__sections[$section] = [];
        }

        $this->__sections[$section][] = $content;

        return $this;
    }

    /**
     * @param string $section
     * @return boolean
     */
    public function hasSection($section)
    {
        return isset($this->__sections[$section]) && !empty($this->__sections[$section]);
    }

    /**
     * @param string $section
     * @return NULL
     */
    public function getSection($section)
    {
        if ($this->hasSection($section)) {
            return $this->__sections[$section];
        }

        return null;
    }

    /**
     * @param string|array $section
     * @return View
     */
    public function clearSection($section)
    {
        if (!is_array($section)) {
            $section = [$section];
        }

        foreach ($section as $sec) {
            if (isset($this->__sections[$sec])) {
                $this->__sections[$sec] = [];
            }
        }

        return $this;
    }

    /**
     * @param string $section
     * @param string $default
     * @param string $glue
     * @return string
     */
    public function renderSection($section, $default = \null, $glue = "\n")
    {
        if (!isset($this->__sections[$section])) {
            return $default;
        }

        return \implode($glue, (array) $this->__sections[$section]);
    }

    /**
     * @param array $sections
     * @return View
     */
    public function setSections(array $sections)
    {
        $this->__sections = $sections;

        return $this;
    }

    /**
     * @return array
     */
    public function getSections()
    {
        return $this->__sections;
    }

    /**
     * @deprecated
     * @param string $module
     * @return View
     */
    public function widget($module)
    {
        $this->__module = $module;

        return $this;
    }

    /**
     * @param string $module
     * @return View
     */
    public function helper($module)
    {
        $this->__module = $module;

        return $this;
    }

    /**
     * @param string $method
     * @param array $params
     * @throws CoreException
     * @return mixed
     */
    public function __call($method, $params)
    {
        $method = \ucfirst($method);

        if (!isset(static::$__helpers[$method])) {

            $search = [];
            $modules = [];

            if (\null !== $this->__module) {
                $modules = [$this->__module];
            } else {
                $app = SharedContainer::getInstance()->get('app');
                if ($app instanceof Application) {
                    $modules = \array_keys($app->getModules());
                }
            }

            foreach ($modules as $module) {
                $search[] = $helper = $module . '\\View\\Helper\\' . \ucfirst($method);
                if (\class_exists($helper, \true)) {
                    static::$__helpers[$method] = new $helper($this);
                    break;
                }
            }

            if (!isset(static::$__helpers[$method])) {
                $search[] = $helper = 'Micro\\View\\Helper\\' . \ucfirst($method);
                if (\class_exists($helper, \true)) {
                    static::$__helpers[$method] = new $helper($this);
                }
            }

            if (!isset(static::$__helpers[$method])) {
                throw new CoreException('Invalid view helper: [' . \implode('], [', $search) . ']', 500);
            }
        }

        $this->__module = \null;

        return \call_user_func_array(static::$__helpers[$method], $params);
    }

    /**
     * @param boolean $flag
     * @return View
     */
    public function setRenderParent($flag)
    {
        $this->__renderParent = (bool) $flag;

        return $this;
    }

    /**
     * @param string $suffix
     * @return View
     */
    public function setSuffix($suffix)
    {
        $this->__suffix = $suffix;

        return $this;
    }
}