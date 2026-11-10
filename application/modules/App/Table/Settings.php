<?php

namespace App\Table;

use Micro\Database\Table\TableAbstract;

class Settings extends TableAbstract
{
    protected $_name = 'Settings';

    public static function get($key, $default = null, $domainId = null)
    {
        $settings = static::fetchCachedSettings($domainId);
        return isset($settings[$key]) ? $settings[$key] : $default;
    }

    public static function fetchCachedSettings($domain = null)
    {
        static $settings;

        $domainId = !is_null($domain) ? $domain : (defined('DOMAIN') ? DOMAIN : 0);

        if (!empty($settings[$domainId])) {
            return $settings[$domainId];
        }

        $callback = function () use($domainId) {
            $result = [];
            foreach ((new Settings())->fetchAll(['domainId = ?' => $domainId]) as $row) {
                $result[$row['key']] = $row['value'];
            }
            return $result;
        };

        $settings[$domainId] = cache_data('json', 'Settings_' . $domainId, $callback, ['Settings']);

        return $settings[$domainId];

    }

    public static function removeCache($domain = null)
    {
        $domainId = !is_null($domain) ? $domain : (defined('DOMAIN') ? DOMAIN : 0);

        cache('json')->remove('Settings_' . $domainId);

    }

    public function getSettings($domain = null)
    {
        $domainId = !is_null($domain) ? $domain : (defined('DOMAIN') ? DOMAIN : 0);

        $select = $this->createHybridSelect()
                       ->joinInner('SettingsSections', 'Settings.sectionId = SettingsSections.id', ['section' => 'name'])
                       ->where('domainId = ?', $domainId)
                       ->order('sort ASC')
                       ->order('Settings.id ASC');

        $settings = [];
        foreach ($this->getAdapter()->fetchAll($select) as $setting) {
            if (!isset($settings[$setting['section']])) {
                $settings[$setting['section']] = [];
            }
            $settings[$setting['section']][$setting['key']] = $setting;
        }

        return $settings;
    }
}