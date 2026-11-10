<?php

namespace Micro\Validate;

class Domain extends Regex
{
    protected $pattern = '/^((?:https?|ftp):\/\/)?(?:www\.|www2\.)?[a-z0-9-]+(?:\.[a-z]{2,6})+(?:\/[^\s]*)?$/i';

    protected $fullPattern = true;

    protected $templates = [
        self::ERROR => 'Невалиден домейн'
    ];

    public function toClient()
    {
        return [
            'name' => 'domain',
            'message' => $this->templates[self::ERROR],
        ];
    }
}
