<?php

namespace Files\Helper;

use Files\Table\Files as TableFiles;

class Files
{
    public static function resize($fileRow, $settings)
    {
        if (isset($settings['thumbs'])) {

            $filesDir = TableFiles::getDir();
            $filePath = public_path($filesDir . DIRECTORY_SEPARATOR . $fileRow['target'] . DIRECTORY_SEPARATOR . $fileRow['file']);

            if (!is_file($filePath)) {
                return;
            }

            $thumbsDir = public_path($filesDir . DIRECTORY_SEPARATOR . $fileRow['target'] . DIRECTORY_SEPARATOR . 'thumbs');

            if (!is_dir($thumbsDir)) {
                mkdir($thumbsDir, 0777, true);
            }

            foreach ($settings['thumbs'] as $thumbType => $thumbSettings) {

                try {

                    $handler = isset($thumbSettings['handler']) ? $thumbSettings['handler'] : null;
                    $method  = isset($thumbSettings['method']) ? $thumbSettings['method'] : null;
                    $params  = isset($thumbSettings['params']) ? $thumbSettings['params'] : [];

                    if ($handler === null || !class_exists($handler, true) || $method === null) {
                        continue;
                    }

                    $thumbPath = $thumbsDir . DIRECTORY_SEPARATOR . $thumbType . '_' . $fileRow['file'];

                    if (is_file($thumbPath)) {
                        continue;
                    }

                    $imageSize = @getimagesize($filePath);

                    if (!$imageSize || !isset($imageSize['bits'])) {
                        continue;
                    }

                    $class = new $handler($filePath);

                    if (method_exists($class, $method) && method_exists($class, 'save')) {

                        call_user_func_array([$class, $method], $params);

                        $class->save($thumbPath);
                    }

                } catch (\Exception $e) {

                }
            }
        }
    }

    public static function checkLinkType($url) {

        $mime = [];

        if(preg_match('/youtu\.be/i', $url) || preg_match('/youtube\.com\/watch/i', $url)) {
            $mime['type'] = 'video';
            $mime['media'] = 'youtube';
        }
        elseif (preg_match('/vimeo\.com/i', $url)) {
            $mime['type'] = 'video';
            $mime['media'] = 'vimeo';
        }
        elseif (preg_match('/vbox7/i', $url)) {
            $mime['type'] = 'video';
            $mime['media'] = 'vbox7';
        }
        elseif (preg_match('/google\.com\/maps/i', $url)) {
            $mime['type'] = 'application';
            $mime['media'] = 'google-maps';
        } 
        else {
            $mime['type'] = 'link';
            $mime['media'] = 'unknow';
        }

        return $mime;

    }


    public static function youtubeVideoId($url)
    {

        $pattern = '/^.*((youtu.be\/)|(v\/)|(\/u\/\w\/)|(embed\/)|(watch\?))\??v?=?([^#\&\?]*).*/';
        preg_match($pattern, $url, $matches);
        if (count($matches) && strlen($matches[7]) == 11)
        {
            return $matches[7];
        }


        return '';
    }

    public static function vimeoVideoId($url)
    {

        $pattern = '/\/\/(www\.)?vimeo.com\/(\d+)($|\/)/';
        preg_match($pattern, $url, $matches);
        if (count($matches))
        {
            return $matches[2];
        }

        return '';
    }


}