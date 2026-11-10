<?php

namespace Micro\Validate\File;

use Micro\Validate\ValidateAbstract;
use Micro\Http\UploadedFile;

class Extension extends ValidateAbstract implements FileInterface
{
    const EXTENSION = 'extension';
    const ACCEPT = 'accept';

    protected $templates = [
        self::ERROR => 'Invalid format / extension',
        self::EXTENSION => 'Invalid extension. Allowed extensions are: %s',
        self::ACCEPT => 'Invalid format. Allowed formats are: %s',
    ];

    protected $extensions = [];

    protected $accept = [];

    protected $sensitive = \false;

    public function getAccept()
    {
        return $this->accept;
    }

    public function setAccept(array $accept)
    {
        $this->accept = $accept;
    }

    public function getExtensions()
    {
        return $this->extensions;
    }

    public function setExtensions(array $extensions)
    {
        $extensions = array_map(function ($el) { return '.' . ltrim($el, '.'); }, $extensions);

        $this->extensions = $extensions;
    }

    public function setSensitive($sensitive)
    {
        $this->sensitive = (bool) $sensitive;
    }

    public function isValid($value)
    {
        if (empty($this->accept) && empty($this->extensions)) {
            return \true;
        }

        if ($value instanceof UploadedFile) {

            if ($value->getError() === \UPLOAD_ERR_NO_FILE) {
                return \true;
            }

            if (!empty($this->extensions)) {

                $extension  = '.' . \pathinfo($value->getClientFilename(), \PATHINFO_EXTENSION);
                $extensions = $this->extensions;

                if ($this->sensitive === \false) {
                    $extension  = \strtolower($extension);
                    $extensions = \array_map('strtolower', $extensions);
                }

                if (!\in_array($extension, $extensions)) {
                    $this->messages[] = [$this->templates[self::EXTENSION], \implode(', ', $extensions)];
                    return \false;
                }
            }

            if (!empty($this->accept)) {

                $type   = $value->getClientMediaType();
                $accept = $this->accept;

                if ($this->sensitive === \false) {
                    $type   = \strtolower($type);
                    $accept = \array_map('strtolower', $accept);
                }

                if (!\in_array($type, $accept)) {
                    $this->messages[] = [$this->templates[self::ACCEPT], \implode(', ', $this->accept)];
                    return \false;
                }
            }
        }

        return \true;
    }
}
