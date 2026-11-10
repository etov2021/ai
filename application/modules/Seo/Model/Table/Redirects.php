<?php

namespace Seo\Model\Table;

use Micro\Database\Table\TableAbstract;

class Redirects extends TableAbstract
{
    protected $_name = 'SeoRedirects';

    public function setRedirects(array $redirects)
    {
        $this->getAdapter()->query('TRUNCATE TABLE ' . $this->_name);

        if (isset($redirects['pattern'])) {

            foreach ($redirects['pattern'] as $k => $v) {

                $pattern = $v;
                $replacement = $redirects['replacement'][$k];
                $code = (int) $redirects['code'][$k] ?: 301;

                if (empty($pattern) || empty($replacement)) {
                    continue;
                }

                $this->insert([
                    'pattern' => $pattern,
                    'replacement' => $replacement,
                    'code' => $code,
                ]);
            }
        }

        cache('json')->remove($this->_name);
    }

    public function getRedirects()
    {
        return cache_data('json', $this->_name, function () {
            return $this->getAdapter()->fetchAll('SELECT * FROM ' . $this->_name);
        });
    }
}