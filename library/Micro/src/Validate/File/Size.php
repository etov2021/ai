<?php

namespace Micro\Validate\File;

use Micro\Validate\ValidateAbstract;
use Micro\Http\UploadedFile;

class Size extends ValidateAbstract implements FileInterface
{
    protected $templates = [
        self::ERROR => 'Размерът на файла трябва да бъде по-малък от %s',
    ];

    protected $size;

    public function setSize($size)
    {
        $this->size = (float) $size;
    }

    public function isValid($value)
    {
        if ($this->size !== \null
            && $value instanceof UploadedFile
            && $value->getError() === \UPLOAD_ERR_OK
        ) {
            if ($value->getSize() > ($this->size * 1024 * 1024)) {
                $this->messages[] = [$this->templates[self::ERROR], $this->size . 'MB'];
                return \false;
            }
        }

        return \true;
    }
}
