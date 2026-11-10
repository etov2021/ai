<?php

namespace Micro\Validate\File;

use Micro\Validate\ValidateAbstract;
use Micro\Http\UploadedFile;

class Upload extends ValidateAbstract implements FileInterface
{
    const INI_SIZE       = 'fileUploadErrorIniSize';
    const FORM_SIZE      = 'fileUploadErrorFormSize';
    const PARTIAL        = 'fileUploadErrorPartial';
    const NO_FILE        = 'fileUploadErrorNoFile';
    const NO_TMP_DIR     = 'fileUploadErrorNoTmpDir';
    const CANT_WRITE     = 'fileUploadErrorCantWrite';
    const EXTENSION      = 'fileUploadErrorExtension';
    const ATTACK         = 'fileUploadErrorAttack';
    const UNKNOWN        = 'fileUploadErrorUnknown';

    protected $templates = [
        self::INI_SIZE       => "File '%s' exceeds the defined ini size",
        self::FORM_SIZE      => "File '%s' exceeds the defined form size",
        self::PARTIAL        => "File '%s' was only partially uploaded",
        self::NO_FILE        => "File '%s' was not uploaded",
        self::NO_TMP_DIR     => "No temporary directory was found for file '%s'",
        self::CANT_WRITE     => "File '%s' can't be written",
        self::EXTENSION      => "A PHP extension returned an error while uploading the file '%s'",
        self::ATTACK         => "File '%s' was illegally uploaded. This could be a possible attack",
        self::UNKNOWN        => "Unknown error while uploading file '%s'"
    ];

    protected $required = \false;

    public function setRequired($required)
    {
        $this->required = (bool) $required;
    }

    public function isValid($value)
    {
        $error = \null;
        $text  = '';

        if ($value instanceof UploadedFile) {

            $text = $value->getClientFilename();

            switch ($value->getError()) {
                case 0:
                    if (!\is_uploaded_file($value->file)) {
                        $error = self::ATTACK;
                    }
                    break;
                case 1:
                    $error = self::INI_SIZE;
                    break;
                case 2:
                    $error = self::FORM_SIZE;
                    break;
                case 3:
                    $error = self::PARTIAL;
                    break;
                case 4:
                    if ($this->required) {
                        $error = self::NO_FILE;
                    }
                    break;
                case 6:
                    $error = self::NO_TMP_DIR;
                    break;
                case 7:
                    $error = self::CANT_WRITE;
                    break;
                case 8:
                    $error = self::EXTENSION;
                    break;
                default:
                    $error = self::UNKNOWN;
                    break;
            }
        }

        if (!empty($error)) {
            $this->messages[] = [$this->templates[$error], ($text ?: 'unknown')];
            return \false;
        }

        return \true;
    }
}
