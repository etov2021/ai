<?php

namespace Micro\Helper;

use Micro\Session\SessionNamespace;

class Flash
{
    /**
     * @var \Micro\Session\SessionNamespace
     */
    protected static $session;

    public function __construct()
    {
        self::$session = new SessionNamespace('flashMessage');

        if (!isset(self::$session->messages)) {
            self::$session->messages = [];
        }
    }

    /**
     * @param string $message
     * @param string $type
     * @return Flash
     */
    public function setMessage($message = \null, $type = 'success')
    {
        if ($message === \null) {
            $message = 'Информацията е записана';
        }

        self::$session->messages[] = [
            'message' => $message,
            'type'    => $type
        ];

        return $this;
    }

    /**
     * @return array
     */
    public function getMessages()
    {
        $messages = [];

        if (isset(self::$session->messages)) {
            $messages = self::$session->messages;
            self::$session->unsetAll();
        }

        return $messages;
    }

    /**
     * @return string
     */
    public function getFirstMessage()
    {
        $messages = $this->getMessages();

        if (empty($messages)) {
            return '';
        }

        return $messages[0]['message'];
    }
}