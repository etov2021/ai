<?php

namespace Micro\Application\Controller;

use Micro\Application\Utils;
use Micro\Http\Response\RedirectResponse;

trait FiltersTrait
{
    protected $filtersEncode;
    protected $filtersDecode;

    /**
     * Handle filters
     * @param string $key
     * @return RedirectResponse|array
     */
    protected function handleFilters($key = 'filters', $clearParams = ['id' => \null, 'page' => \null, 'sort' => \null])
    {
        if (!isset($clearParams[Crud::CURRENT_LANG_KEY])) {
            $clearParams[Crud::CURRENT_LANG_KEY] = \null;
        }

        $filters = $this->request->getParam($key);

        if ($this->request->isPost()) {

            $post = $this->request->getPost($key, []);

            if (isset($post['reset'])) {
                return new RedirectResponse(\route(\null, [$key => \null] + $clearParams));
            }

            if (isset($post['filter'])) {

                unset($post['filter']);

                $post = Utils::arrayMapRecursive('trim', $post, true);

                foreach ($post as $k => $v) {
                    if (\is_object($v)
                        || (\is_array($v) && empty($v))
                        || (\is_scalar($v) && \trim((string) $v) === '')
                    ) {
                        unset($post[$k]);
                    }
                }

                $value = $this->encodeFilters($post);

                if (empty($value)) {
                    $value = \null;
                }

                return new RedirectResponse(\route(\null, [$key => $value] + $clearParams));
            }
        }

        if ($filters) {
            $filters = $this->decodeFilters($filters);
        } else {
            $filters = [];
        }

        return $filters;
    }

    protected function encodeFilters(array $data)
    {
        if (is_callable($this->filtersEncode)) {
            return call_user_func($this->filtersEncode, $data);
        }

        return (!empty($data) ? Utils::base64urlEncode(\http_build_query($data)) : \null);

        /* $json = \json_encode($data);
         $hash = \sha1($json);
         \file_put_contents(\data_path('filters/' . $hash . '.json'), $json);

         return (!empty($data) ? $hash : \null); */
    }

    protected function decodeFilters($filters)
    {
        if (is_callable($this->filtersDecode)) {
            return call_user_func($this->filtersDecode, $filters);
        }

        $result = [];

        if (!is_array($filters)) {
            \parse_str(Utils::base64urlDecode($filters, \true), $result);
        } else {
            $result = $filters;
        }

        if (empty($result)) {
            $result = [];
        }

        return $result;

        /* $fileFilter = \data_path('filters/' . $filters . '.json');

        if (\is_file($fileFilter)) {
        $filters = \json_decode(\file_get_contents($fileFilter), \true);
        $filters = !\is_array($filters) ? [] : $filters;
        } else {
        $filters = [];
        }

        return $filters; */
    }

    protected function setFiltersEncode(callable $callback)
    {
        $this->filtersEncode = $callback;
    }

    protected function setFiltersDecode(callable $callback)
    {
        $this->filtersDecode = $callback;
    }
}