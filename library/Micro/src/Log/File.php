<?php

namespace Micro\Log;

use Psr\Log\LoggerInterface as Logger;
use Psr\Log\AbstractLogger as AbstractLogger;

class File extends AbstractLogger implements Logger
{
    private $enabled;
    private $strict;
    private $path;
    private $size = 2097152; // 2M
    private $messages = [];
    private $bannedCodes = [];
    private $bannedMessages = [];

    public function __construct(?array $options = null)
    {
        if (isset($options['enabled'])) {
            $this->enabled = (bool) $options['enabled'];
        }

        if (isset($options['strict'])) {
            $this->strict = (bool) $options['strict'];
        }

        if (isset($options['size'])) {
            $this->size = (int) $options['size'];
        }

        if (isset($options['banned_codes'])) {
            $this->bannedCodes = (array) $options['banned_codes'];
        }

        if (isset($options['banned_messages'])) {
            $this->bannedMessages = (array) $options['banned_messages'];
        }

        if (isset($options['path']) && $options['path']) {
            $this->path = $options['path'];
            if (!is_dir($this->path)) {
                @mkdir($this->path, 0777, true);
            }
        }
    }

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        static $strictInit;

        $message = (string) $message;

        if (!isset($this->messages[$level])) {
            $this->messages[$level] = [];
        }

        $this->messages[$level][] = $message;

        if (!$this->enabled || !$this->path) {
            return;
        }

        if (isset($_SERVER['HTTP_USER_AGENT']) && preg_match('~bot~ius', $_SERVER['HTTP_USER_AGENT'])) {
            return;
        }

        if (isset($context['code']) && in_array($context['code'], $this->bannedCodes)) {
            return;
        }

        if (isset($this->bannedMessages) && is_array($this->bannedMessages) && !empty($this->bannedMessages)) {
            foreach ($this->bannedMessages as $bannedMessage) {
                if (preg_match('~' . preg_quote($bannedMessage) . '~ius', $message)) {
                    return;
                }
            }
        }

        $type = 'logs';

        if (isset($context['type'])) {
            $type = $context['type'];
            unset($context['type']);
        }

        if (isset($context['message'])) {
            unset($context['message']);
        }

        $context['REMOTE_ADDR'] = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : (user_ip() ?: 'UNKNOWN IP');
        $context['REQUEST_URI'] = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : 'NO REQUEST URI';

        $server = [];
        foreach ($_SERVER as $k => $v) {
            if (!in_array($k, ['HTTP_USER_AGENT', 'HTTP_CONNECTION', 'HTTP_CACHE_CONTROL', 'HTTP_ACCEPT', 'HTTP_ACCEPT_ENCODING', 'HTTP_ACCEPT_LANGUAGE', 'SCRIPT_FILENAME', 'REDIRECT_URL', 'QUERY_STRING', 'SCRIPT_NAME', 'PHP_SELF', 'REQUEST_TIME_FLOAT', 'REQUEST_TIME'])) {
                continue;
            }
            $server[$k] = $v;
        }

        $context['SERVER'] = json_encode($server);
        //$context['TRACE'] = json_encode(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));

        $extra = '';

        foreach ($context as $k => $v) {
            if (is_int($k)) {
                continue;
            }
            $extra .= " - " . $k . " [" . (is_scalar($v) ? $v : json_encode($v)) . "]";
        }

        $fileName = rtrim($this->path, '/') . '/' . $type . '.' . $level . '.txt';

        if (is_file($fileName)) {
            if (filesize($fileName) > $this->size) {
                $baseName = basename($fileName);
                $newName = preg_replace('/\.([a-z]+)$/i', '.' . time() . '.$1', $baseName);
                @copy($fileName, str_ireplace($baseName, $newName, $fileName));
                @unlink($fileName);
            }
        }

        $chmod = false;

        if (!is_file($fileName)) {
            $chmod = true;
        }

        file_put_contents($fileName, '[' . date('d M Y (H:i:s)') . '] "' . $message . '"' . $extra . "\n", FILE_APPEND | LOCK_EX);

        if ($chmod) {
            @chmod($fileName, 0777);
        }

        if ($strictInit === null && $this->strict && in_array($level, ['notice', 'warning'])) {
            $strictInit = true;
            throw new \Exception($message, 500);
        }
    }

    public function getMessages()
    {
        return $this->messages;
    }
}