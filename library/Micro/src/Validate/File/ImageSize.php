<?php

namespace Micro\Validate\File;

use Micro\Validate\ValidateAbstract;
use Micro\Http\UploadedFile;

class ImageSize extends ValidateAbstract implements FileInterface
{
    protected $templates = [
        self::ERROR => 'Пропорциите на файла трябва да бъдат %sx%s',
    ];

    protected $width;
    protected $height;

    public function setWidth($value)
    {
        $this->width = $value;
    }


    public function setHeight($value)
    {
        $this->height = $value;
    }

    public function isValid($value)
    {
        if ($value instanceof UploadedFile
            && $value->getError() === \UPLOAD_ERR_OK
        ) {
            $size = @getimagesize($value->file);
            if ($size === \false
                || $size[0] === 0
                || $size[1] === 0
                || !isset($size['bits'])
                || ($this->width != '*' && $size[0] != $this->width)
                || ($this->height != '*' && $size[1] != $this->height)
            ) {
                $this->messages[] = [$this->templates[self::ERROR], $this->width, $this->height];
                return \false;
            }
        }

        return \true;
    }
}
