<?php

namespace Micro\Assets;

class Bundle
{
    protected $name;

    protected $js = [];

    protected $css = [];

    protected $minify = false;

    protected $publicDir;

    protected $publicUrl;

    public function __construct($name, array $params = [])
    {
        $this->name = $name;

        if (!empty($params)) {
            $this->configure($params);
        }
    }

    public function configure(array $params)
    {
        if (isset($params['minify'])) {
            $this->minify = (bool) $params['minify'];
        }

        if (isset($params['publicDir'])) {
            $this->publicDir = realpath($params['publicDir']);
        }

        if (isset($params['publicUrl'])) {
            $this->publicUrl = rtrim(strtr($params['publicUrl'], DIRECTORY_SEPARATOR, '/'), '/');
            if (empty($this->publicUrl)) {
                $this->publicUrl = '/';
            }
        }

        if (isset($params['assets'])) {
            $this->add($params['assets']);
        }

        return $this;
    }

    public function add($file)
    {
        if (is_array($file)) {
            foreach ($file as $f) {
                $this->add($f);
            }
            return $this;
        }

        if (preg_match('~\.js$~', $file)) {
            $this->addJs($file);
        }

        if (preg_match('~\.css$~', $file)) {
            $this->addCss($file);
        }

        return $this;
    }

    public function addJs($file)
    {
        if (is_array($file)) {
            foreach ($file as $f) {
                $this->addJs($f);
            }
            return $this;
        }

        $asset = $this->findAsset($file);

        if ($asset !== null && !isset($this->js[$asset['file']])) {
            $this->js[$asset['file']] = $asset;
        }

        return $this;
    }

    public function addCss($file)
    {
        if (is_array($file)) {
            foreach ($file as $f) {
                $this->addCss($f);
            }
            return $this;
        }

        $asset = $this->findAsset($file);

        if ($asset !== null && !isset($this->css[$asset['file']])) {
            $this->css[$asset['file']] = $asset;
        }

        return $this;
    }

    protected function findAsset($file)
    {
        $asset = false;
        $inPublic = false;
        $isRemoteLink = false;
        $source = $file;

        $file = (string) $file;

        if ($this->isRemoteLink($file)) {
            $asset = $file;
            $isRemoteLink = true;
        } else {
            $asset = realpath($file);
            if ($asset === false) {
                if (strpos($file, $this->publicUrl) === 0) {
                    if ($this->publicUrl === '/') {
                        $asset = substr($file, 1);
                    } else {
                        $asset = substr($file, strlen($this->publicUrl) + 1);
                    }
                    $inPublic = true;
                } else {
                    $file = ltrim($file, '/\\');
                    if (realpath($this->publicDir . DIRECTORY_SEPARATOR . $file)) {
                        $asset = $file;
                        $inPublic = true;
                    }
                }
            }
        }

        if ($asset !== false) {
            return [
                'is_remote' => $isRemoteLink,
                'in_public' => $inPublic,
                'file'      => $asset,
                'source'    => $source,
            ];
        }

        return null;
    }

    public function js(array $attributes = [])
    {
        if (empty($this->js)) {
            return '';
        }

        unset($attributes['src']);

        if (!array_key_exists('type', $attributes)) {
            $attributes['type'] = 'text/javascript';
        }

        $attributes = $this->buildTagAttributes($attributes);

        $output = '';

        if ($this->minify === true) {
            $output .= '<script src="' . $this->minifyJs() . '"' . $attributes . "></script>\n";
        } else {
            foreach ($this->js as $asset) {
                $output .= '<script src="' . $this->getCachedAsset('js', $asset) . '"' . $attributes . "></script>\n";
            }
        }

        return $output;
    }

    protected function minifyJs()
    {
        return $this->minify($this->js, 'js', 'js', function ($buffer) {
            return Minify\Js::minify($buffer);
        });
    }

    public function css(array $attributes = [])
    {
        if (empty($this->css)) {
            return '';
        }

        unset($attributes['href']);

        if (!array_key_exists('type', $attributes)) {
            $attributes['type'] = 'text/css';
        }

        if (!array_key_exists('rel', $attributes)) {
            $attributes['rel'] = 'stylesheet';
        }

        $attributes = $this->buildTagAttributes($attributes);

        $output = '';

        if ($this->minify === true) {
            $output .= '<link href="' . $this->minifyCss() . '"' . $attributes . " />\n";
        } else {
            foreach ($this->css as $asset) {
                $output .= '<link href="' . $this->getCachedAsset('css', $asset) . '"' . $attributes . " />\n";
            }
        }

        return $output;
    }

    protected function minifyCss()
    {
        return $this->minify($this->css, 'css', 'css', function ($buffer, $asset) {
            if ($asset['source'] instanceof File) {
                if ($asset['is_remote']) {
                    $currentDir = null;
                } elseif ($asset['in_public']) {
                    $currentDir = dirname($this->publicDir . DIRECTORY_SEPARATOR . $asset['file']);
                } else {
                    $currentDir = dirname($asset['file']);
                }
                if ($currentDir !== null) {
                    $buffer = Css\UriRewriter::rewrite($buffer, $currentDir);
                }
            }
            return (new Minify\Css())->run($buffer);
        });
    }

    protected function minify(array $assets, $dir, $extension, \Closure $minifer)
    {
        $collection = $this->name . DIRECTORY_SEPARATOR . $dir;

        $minDir = $this->publicDir . DIRECTORY_SEPARATOR . 'asset' . DIRECTORY_SEPARATOR . 'min' . DIRECTORY_SEPARATOR . $collection;

        if (!is_dir($minDir)) {
            mkdir($minDir, 0777, true);
        }

        $fileName = $this->calcHash($assets) . '.' . $extension;

        $minFile = $minDir . DIRECTORY_SEPARATOR . $fileName;

        if (!is_file($minFile)) {

            $buffer = '';

            foreach ($assets as $asset) {
                if ($asset['is_remote']) {
                    $content = (string) @file_get_contents($asset['file']);
                } elseif ($asset['in_public']) {
                    $content = file_get_contents($this->publicDir . DIRECTORY_SEPARATOR . $asset['file']);
                } else {
                    $content = file_get_contents($asset['file']);
                }
                $buffer .= $minifer->__invoke($content, $asset);
            }

            file_put_contents($minFile, $buffer);
        }

        $publicUrl = $this->publicUrl;

        if ($publicUrl === '/') {
            $publicUrl = '';
        }

        return $publicUrl . '/asset/min/' . str_replace('\\', '/', $collection) . '/' . $fileName;
    }

    protected function getCachedAsset($dir, $asset)
    {
        $publicUrl = $this->publicUrl;

        if ($publicUrl === '/') {
            $publicUrl = '';
        }

        if ($asset['is_remote']) {
            return $asset['file'];
        }

        if ($asset['in_public']) {
            return $publicUrl . '/' . $asset['file'];
        }

        $collection = $this->name . DIRECTORY_SEPARATOR . $dir;

        $cacheDir = $this->publicDir . DIRECTORY_SEPARATOR . 'asset' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . $collection;

        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0777, true);
        }

        $cacheFilename = pathinfo($asset['file'], PATHINFO_FILENAME) . '.' . md5($asset['file'] . filemtime($asset['file'])) . '.' . pathinfo($asset['file'], PATHINFO_EXTENSION);

        $cacheFile = $cacheDir . DIRECTORY_SEPARATOR . $cacheFilename;

        if (!is_file($cacheFile)) {

            $buffer = file_get_contents($asset['file']);

            if ($dir === 'css' && $asset['source'] instanceof File) {
                $buffer = Css\UriRewriter::rewrite($buffer, dirname($asset['file']));
            }

            file_put_contents($cacheFile, $buffer);
        }

        return $publicUrl . '/asset/cache/' . str_replace('\\', '/', $collection) . '/' . $cacheFilename;
    }

    protected function calcHash(array $assets)
    {
        $timestamps = [];

        foreach($assets as $asset) {

            if ($asset['is_remote']) {
                continue;
            }

            $file = $asset['file'];

            if ($asset['in_public']) {
                $file = $this->publicDir . DIRECTORY_SEPARATOR . $asset['file'];
            }

            $timestamps[] = filemtime($file);
        }

        return md5(http_build_query($assets) . implode($timestamps));
    }

    protected function isRemoteLink($link)
    {
        return ('http://' === substr($link, 0, 7) || 'https://' === substr($link, 0, 8) || '//' === substr($link, 0, 2));
    }

    protected function buildTagAttributes(array $attributes)
    {
        $html = array();

        foreach ($attributes as $key => $value)
        {
            if (is_null($value)) {
                continue;
            }

            if (is_numeric($key)) {
                $key = $value;
            }

            $html[] = $key . '="' . htmlentities($value, ENT_QUOTES, 'UTF-8', false) . '"';
        }

        return (count($html) > 0) ? ' ' . implode(' ', $html) : '';
    }
}