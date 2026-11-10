<?php

namespace Micro\Validate;

class Remote extends ValidateAbstract implements ClientValidateInterface
{
    protected $templates = [
        self::ERROR => 'Невалидна стойност'
    ];

    protected $url;

    protected $params = [];

    protected $curl = true;

    protected $curlOptions = [];

    public function isValid($value)
    {
        $content = $this->getResponse($value);

        if ($content && ($content['success'] ?? false) === false) {
            $this->messages[] = $content['message'] ?? $this->templates[self::ERROR];
            return false;
        }

        return true;
    }

    public function getResponse($value)
    {
        if (empty($this->url) || empty($this->curl)) {
            return null;
        }

        $fields = [
            'value' => $value,
        ];

        $fields += ($this->params ?? []);

        $url = $this->url;

        if (!preg_match('~^(https?):~', $url)) {
            $url = server_url($url);
        }

        $curl = curl_init($url);

        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($fields));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        if (!empty($this->curlOptions)) {
            curl_setopt_array($curl, $this->curlOptions);
        }

        $content = curl_exec($curl);

        curl_close($curl);

        $content = $content ? json_decode($content, true) : null;

        return $content;
    }

    public function setUrl($url)
    {
        $this->url = $url;
    }

    public function setParams(array $params)
    {
        $this->params = $params;
    }

    public function setCurl($curl)
    {
        $this->curl = (bool) ($curl ? 1 : 0);
    }

    public function setCurlOptions(array $options)
    {
        $this->curlOptions = $options;
    }

    public function toClient()
    {
        return [
            'name' => 'remote',
            'message' => $this->templates[self::ERROR],
            'options' => [
                'url' => $this->url,
                'params' => (object) $this->params,
            ],
        ];
    }
}
