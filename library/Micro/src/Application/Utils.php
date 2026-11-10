<?php

namespace Micro\Application;

class Utils
{
    /**
     * Delete directory recursive
     * @param string $directory
     */
    public static function removeDirectory($directory)
    {
        $inner = new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS);
        $outer = new \RecursiveIteratorIterator($inner, \RecursiveIteratorIterator::SELF_FIRST);

        // remove the files first
        foreach ($outer as $file) {
            if ($file->isFile()) {
                @\unlink($file);
            }
        }

        // remove the sub-directories next
        $files = \iterator_to_array($outer);
        foreach (\array_reverse($files) as $file) {
            /** @var \SplFileInfo $file */
            if ($file->isDir()) {
                @\rmdir($file);
            }
        }

        // finally the directory itself
        @\rmdir($directory);
    }

    public static function classUsesDeep($class, $autoload = \true)
    {
        $traits = [];

        // Get traits of all parent classes
        do {
            $traits = \array_merge(\class_uses($class, $autoload), $traits);
        } while (($class = \get_parent_class($class)) !== \false);

        // Get traits of all parent traits
        $traitsToSearch = $traits;

        while (!empty($traitsToSearch)) {
            $newTraits = \class_uses(\array_pop($traitsToSearch), $autoload);
            $traits = \array_merge($newTraits, $traits);
            $traitsToSearch = \array_merge($newTraits, $traitsToSearch);
        };

        foreach ($traits as $trait => $same) {
            $traits = \array_merge(\class_uses($trait, $autoload), $traits);
        }

        return \array_unique($traits);
    }

    public static function arrayMapRecursive($fn, $arr, $allowNull = \false)
    {
        $rarr = [];

        foreach ($arr as $k => $v) {
            if (\is_array($v)) {
                $rarr[$k] = self::arrayMapRecursive($fn, $v, $allowNull);
            } else {
                if (\is_scalar($v)) {
                    if ($allowNull && $v == \null) {
                        $rarr[$k] = \null;
                    } else {
                        $rarr[$k] = $fn($v);
                    }
                } else {
                    $rarr[$k] = $v;
                }
            }
        }

        return $rarr;
    }

    public static function decamelize($value)
    {
        return \strtolower(\trim(\preg_replace('/([A-Z])/', '-$1', $value), '-'));
    }

    public static function camelize($value)
    {
        if (\null === $value) {
            return '';
        }

        $value = \preg_replace('/[^a-z0-9-]/ius', '', $value);

        if (\strpos($value, '-') !== \false) {
            $value = \str_replace(' ', '', \ucwords(\str_replace('-', ' ', $value)));
        }

        return $value;
    }

    public static function safeSerialize($s)
    {
        return \base64_encode(\serialize($s));
    }

    public static function safeUnserialize($s)
    {
        return \unserialize(\base64_decode($s));
    }

    public static function base64urlEncode($data)
    {
        return \rtrim(\strtr(\base64_encode($data), '+/', '-_'), '=');
    }

    public static function base64urlDecode($data, $strict = \false)
    {
        $mod = \strlen($data) % 4;
        return \base64_decode(\strtr($data, '-_', '+/') . ($mod ? substr('====', $mod) : ''), $strict);
    }

    public static function toMoney($value, $decimal = \null)
    {
        if ($decimal !== null) {
            return \number_format($value, $decimal, ',', '');
        }

        $value = \round($value, 2);

        $x = $value - (int) ($value);

        if ($x) {
            return \number_format($value, 2, ',', '');
        } else {
            return \number_format($value, 0, ',', '');
        }
    }

    public static function uniord($u)
    {
        $k = \mb_convert_encoding($u, 'UCS-2LE', 'UTF-8');
        $k1 = \ord(\substr($k, 0, 1));
        $k2 = \ord(\substr($k, 1, 1));
        return $k2 * 256 + $k1;
    }

    public static function unichr($intval)
    {
        return \mb_convert_encoding(\pack('n', $intval), 'UTF-8', 'UTF-16BE');
    }

    public static function buildOptions($optionsInput, $value = 0, $emptyOption = null, $emptyOptionValue = "", $translateValue = \false)
    {
        $options = '';

        $isArray = false;

        if (\is_array($value)) {
            $isArray = true;
        }

        if ($emptyOption !== null) {
            $optionsInput = [$emptyOptionValue => $emptyOption] + $optionsInput;
        }

        $isSelected = false;
        foreach ($optionsInput as $optionGroup => $group) {
            if (\is_array($group)) {
                $options .= '<optgroup label="' . $optionGroup . '">';
                $options .= self::buildOptions($group, $value, null, "", $translateValue);
                $options .= '</optgroup>';
            } else {
                $selected = '';
                if ($isArray || $isSelected === false) {
                    foreach ((array) $value as $v) {
                        if ($optionGroup === "") {
                            if ($v === null || $optionGroup === $v) {
                                $isSelected = true;
                                $selected = ' selected="selected"';
                            }
                        } else {
                            if ($optionGroup == $v) {
                                $isSelected = true;
                                $selected = ' selected="selected"';
                            }
                        }
                    }
                }
                $options .= '<option' . $selected . ' value="' . $optionGroup . '">' . ($translateValue ? \translate($group) : $group) . '</option>';
            }
        }

        return $options;
    }

    public static function merge(array $a, array $b, $preserveNumericKeys = \false)
    {
        foreach ($b as $key => $value) {
            if (isset($a[$key]) || \array_key_exists($key, $a)) {
                if (!$preserveNumericKeys && is_int($key)) {
                    $a[] = $value;
                } elseif (\is_array($value) && \is_array($a[$key])) {
                    $a[$key] = static::merge($a[$key], $value, $preserveNumericKeys);
                } else {
                    $a[$key] = $value;
                }
            } else {
                $a[$key] = $value;
            }
        }

        return $a;
    }

    public static function globRecursive($pattern, $flags = 0)
    {
        $files = \glob($pattern, $flags);

        foreach (\glob(dirname($pattern) . '/*', \GLOB_ONLYDIR | \GLOB_NOSORT) as $dir) {
            $files = static::merge($files, static::globRecursive($dir . '/' . \basename($pattern), $flags));
        }

        return $files;
    }

    public static function iteratorToArray($iterator, $recursive = \true)
    {
        if (!\is_array($iterator) && !$iterator instanceof \Traversable) {
            throw new \InvalidArgumentException(__METHOD__ . ' expects an array or Traversable object');
        }

        if (!$recursive) {
            if (\is_array($iterator)) {
                return $iterator;
            }
            return \iterator_to_array($iterator);
        }

        if (\method_exists($iterator, 'toArray')) {
            return $iterator->toArray();
        }

        $array = [];

        foreach ($iterator as $key => $value) {
            if (\is_scalar($value)) {
                $array[$key] = $value;
                continue;
            }
            if ($value instanceof \Traversable) {
                $array[$key] = static::iteratorToArray($value, $recursive);
                continue;
            }
            if (\is_array($value)) {
                $array[$key] = static::iteratorToArray($value, $recursive);
                continue;
            }
            $array[$key] = $value;
        }

        return $array;
    }

    public static function getMimeTypes($mimesPath = \null)
    {
        static $mimes;

        if ($mimes !== \null) {
            return $mimes;
        }

        if ($mimesPath === \null) {
            $mimesPath = data_path('mimes.php');
        }

        $mimeTypes = [];

        if (is_file($mimesPath)) {
            $mimeTypes = include $mimesPath;
            $mimeTypes = is_array($mimeTypes) ? $mimeTypes : [];
        }

        return $mimes = $mimeTypes;
    }

	public static function getExtByMimeTypes($mime)
    {
        $mimes = self::getMimeTypes();
        $mimes = array_flip($mimes);

        $mimeParts = explode("/", $mime);

        if(isset($mimes[$mime])) {
            return [$mimeParts[0],$mimes[$mime]];
        }

        return $mimeParts;

    }

    public static  function parseGitLog()
    {
        $output = [];

        \exec('git log', $output);

        $commits = [];

        $current = \null;

        foreach ($output as $out) {

            if (\strpos($out, 'commit') === 0) {
                $current = \md5($out);
                $commits[$current] = [
                    'commit'  => \trim(\str_replace('commit', '', $out)),
                    'date'    => '',
                    'author'  => '',
                    'message' => [],
                ];
                continue;
            }

            if ($current === \null) {
                continue;
            }

            if (\strpos($out, 'Date') === 0) {
                $commits[$current]['date'] = \trim(\str_replace('Date:', '', $out));
                continue;
            }

            if (\strpos($out, 'Author') === 0) {
                $commits[$current]['author'] = \trim(\str_replace('Author:', '', $out));
                continue;
            }

            $out = \trim($out);

            if (!empty($out)) {
                $commits[$current]['message'][] = $out;
            }
        }

        return [
            'commits' => $commits,
            'output'  => $output
        ];
    }

    public static function getVersion($reloadTime = 86400)
    {
        $filePath = __DIR__ . '/../.version';

        if (\is_file($filePath)) {
            $diff = \time() - \filemtime($filePath);
            if ($diff <= $reloadTime) {
                return \file_get_contents($filePath);
            }
        }

        $result = static::parseGitLog();

        $version = 0;

        if (!empty($result['output'])) {
            $version = \count($result['commits']);
            \file_put_contents($filePath, $version);
        }

        return $version;
    }

    /**
     *
     * @param number $length
     * @return string
     */
    public static function generateRandomString ($length = 10)
    {
        $length = (int) $length;

        $salt = "abchefghjkmnpqrstuvwxyz0123456789";
        srand((double)microtime()*1000000);

        $string = '';
        $i = 0;
        while ($i < $length) {
            $num    = mt_rand() % 33;
            $tmp 	= substr($salt, $num, 1);
            $string = $string . $tmp;
            $i++;
        }

        return $string;
    }

    /**
     * @param string $html
     */
    public static function compress($html)
    {
        preg_match_all('!(<(?:code|pre|script).*>[^<]+</(?:code|pre|script)>)!',$html,$pre);
        $html = preg_replace('!<(?:code|pre).*>[^<]+</(?:code|pre)>!', '#pre#', $html);
        $html = preg_replace('#<!–[^\[].+–>#', '', $html);
        $html = preg_replace('/[\r\n\t]+/', ' ', $html);
        $html = preg_replace('/>[\s]+</', '><', $html);
        $html = preg_replace('/[\s]+/', ' ', $html);
        if (!empty($pre[0])) {
            foreach ($pre[0] as $tag) {
                $html = preg_replace('!#pre#!', $tag, $html,1);
            }
        }
        return $html;
    }
}