<?php

namespace Micro\Helper\Tree;

trait TreeTrait
{
    public function getTree($active = \null)
    {
        $items = $this->loadTree($active);

        foreach ($items as $index => $element) {
            $items[$index]['children'] = $this->getChildren($element['id'], $items);
        }

        foreach ($items as $index => $element) {
            if ($element['parentId'] > 0) {
                unset($items[$index]);
            }
        }

        return array_values($items);
    }

    protected function getChildren($id, $items)
    {
        $children = [];

        foreach ($items as $key => $item) {
            if ($item['parentId'] == $id) {
                $level = $item['level'];
                while (isset($children[$level])) {
                    $level++;
                }
                $children[$level] = $item;
            }
        }

        foreach ($children as $key => $child) {
            $children[$key]['children'] = $this->getChildren($child['id'], $items);
        }

        \ksort($children);

        return $children;
    }

    public function flat(array $tree, $glue = '-',  ?array $exclude = \null)
    {
        static $level = 0;

        $elements = [];

        foreach ($tree as $item) {

            if ($exclude !== \null && \in_array($item['id'], $exclude)) {
                continue;
            }

            $elements[$item['id']] = \trim(\str_repeat($glue, $level) . ' ' . $item['name']);

            if (!empty($item['children'])) {
                $level++;
                $elements += $this->flat($item['children'], $glue, $exclude);
                $level--;
            }
        }

        return $elements;
    }

    public function pairs(array $tree, $fullNames = false, $parentName = '')
    {
        static $level = 0;

        $elements = [];

        foreach ($tree as $item) {

            $name = $fullNames && $parentName ? ($parentName . ' / ' . $item['name']) : $item['name'];

            $elements[$item['id']] = $name;

            if (!empty($item['children'])) {
                $level++;
                $elements += $this->pairs($item['children'], $fullNames, $name);
                $level--;
            }
        }

        return $elements;
    }

    public function toSelectOptions(array $tree,  $glue = '-',  ?array $exclude = \null)
    {
        static $level = 0;

        $elements = [];

        foreach ($tree as $item) {

            if ($exclude !== \null && \in_array($item['id'], $exclude)) {
                continue;
            }

            $name = \trim(\str_repeat($glue, $level) . ' ' . $item['name']);

            if (!empty($item['children'])) {
                $level++;
                $elements[$name] = $this->toSelectOptions($item['children'], $glue, $exclude);
                $level--;
            } else {
                $elements[$item['id']] = $name;
            }
        }

        return $elements;
    }
}