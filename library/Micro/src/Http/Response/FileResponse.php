<?php

namespace Micro\Http\Response;

use Micro\Http\Response;
use Micro\Http\Stream;
use Micro\Http\TempStream;

class FileResponse extends Response
{
    protected $mimeTypes = [

        'txt' => 'text/plain',
        'htm' => 'text/html',
        'html' => 'text/html',
        'php' => 'text/html',
        'css' => 'text/css',
        'js' => 'application/javascript',
        'json' => 'application/json',
        'xml' => 'application/xml',
        'swf' => 'application/x-shockwave-flash',
        'flv' => 'video/x-flv',

        // images
        'png' => 'image/png',
        'jpe' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'jpg' => 'image/jpeg',
        'gif' => 'image/gif',
        'bmp' => 'image/bmp',
        'ico' => 'image/vnd.microsoft.icon',
        'tiff' => 'image/tiff',
        'tif' => 'image/tiff',
        'svg' => 'image/svg+xml',
        'svgz' => 'image/svg+xml',

        // archives
        'zip' => 'application/zip',
        'rar' => 'application/x-rar-compressed',
        'exe' => 'application/x-msdownload',
        'msi' => 'application/x-msdownload',
        'cab' => 'application/vnd.ms-cab-compressed',

        // audio/video
        'mp3' => 'audio/mpeg',
        'qt' => 'video/quicktime',
        'mov' => 'video/quicktime',

        // adobe
        'pdf' => 'application/pdf',
        'psd' => 'image/vnd.adobe.photoshop',
        'ai' => 'application/postscript',
        'eps' => 'application/postscript',
        'ps' => 'application/postscript',

        // ms office
        'doc' => 'application/msword',
        'rtf' => 'application/rtf',
        'xls' => 'application/vnd.ms-excel',
        'ppt' => 'application/vnd.ms-powerpoint',

        // open office
        'odt' => 'application/vnd.oasis.opendocument.text',
        'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
    ];

    protected $name;

    public function __construct($file, $name = \null, $status = 200, array $headers = [])
    {
        if (empty($file) || !\is_file($file)) {
            parent::__construct(new TempStream('File not found'), 404);
            return;
        }

        $this->name = $name;

        $this->marshalBuffers();

        parent::__construct(
            new Stream(\fopen($file, 'rb')),
            $status,
            []
        );

        $headers = $headers + $this->marshalHeaders($file);

        foreach ($headers as $name => $value) {
            $this->withHeader($name, $value);
        }
    }

    protected function marshalBuffers()
    {
        @\ob_end_clean();
        @\ob_implicit_flush(true);

        if (\ini_get('zlib.output_compression'))  {
            \ini_set('zlib.output_compression', 'Off');
        }

        // fix for IE7/8, ticket #183
        if (\function_exists('apache_setenv')) {
            $func = 'apache_setenv';
            $func('no-gzip', '1');
        }
    }

    protected function marshalHeaders($file)
    {
        $fsize    = \filesize($file);
        $pathinfo = \pathinfo($file);
        $ext      = \strtolower($pathinfo['extension']);

        if (\function_exists('finfo_open')) {
            $finfo = \finfo_open(FILEINFO_MIME_TYPE);
            $mime  = \finfo_file($finfo, $file);
        } else {
            $mime  = isset($this->mimeTypes[$ext])
                     ? $this->mimeTypes[$ext]
                     : 'application/octet-stream';
        }

        return [
            'Pragma'              => 'public',
            'Expires'             => '-1',
            'Cache-Control'       => 'public, must-revalidate, post-check=0, pre-check=0',
            'Content-Disposition' => 'attachment; filename="' . ($this->name ? $this->name : $pathinfo['basename']) . '"',
            'Content-Type'        => $mime,
            'Content-length'      => $fsize,
            //'Accept-Ranges'       => 'bytes',
            //'Content-Encoding'    => '',
        ];
    }
}
