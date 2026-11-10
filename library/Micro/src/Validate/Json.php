<?php

namespace Micro\Validate;

use Micro\Validate\ValidateAbstract;

class Json extends ValidateAbstract implements ClientValidateInterface
{
    protected $templates = [
        JSON_ERROR_DEPTH => 'Maximum stack depth exceeded',
        JSON_ERROR_STATE_MISMATCH => 'State mismatch (invalid or malformed JSON)',
        JSON_ERROR_CTRL_CHAR => 'Control character error, possibly incorrectly encoded',
        JSON_ERROR_SYNTAX => 'Syntax error',
        JSON_ERROR_UTF8 => 'Malformed UTF-8 characters, possibly incorrectly encoded',
    ];

	public function isValid($value)
    {
        $test = @json_decode($value);
        $error = json_last_error();
        if ($error !== JSON_ERROR_NONE) {
            $this->messages[] = isset($this->templates[$error]) ? $this->templates[$error] : 'Unknown JSON error';
            return false;
        }

        return true;
    }

    public function toClient()
    {
        return [
            'name' => 'json',
        ];
    }
}