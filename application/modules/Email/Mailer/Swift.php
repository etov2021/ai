<?php

namespace Email\Mailer;

class Swift extends \Swift_Message
{
    protected $mailers = [];

    public function __construct(array $options = [])
    {
        parent::__construct();

        $this->setDefaults($options);
    }

    public function setDefaults(array $options = [])
    {
        $options = $options + config('mail.message', []);

        foreach ($options as $k => $v) {
            try {
                $this->{'set' . ucfirst($k)}($v);
            } catch (\Exception $e) {

            }
        }
    }

    public function send($transport = null)
    {
        if ($transport === null) {

            $type    = config('mail.swift_transport.type', []);
            $options = config('mail.swift_transport.options', []);

            switch ($type) {
                case 'smtp' :
                    if (isset($options['host'])) {
                        $host = $options['host'];
                        unset($options['host']);
                    } else {
                        $host = 'localhost';
                    }
                    if (isset($options['port'])) {
                        $port = $options['port'];
                        unset($options['port']);
                    } else {
                        $port = 25;
                    }
                    if (isset($options['security'])) {
                        $security = $options['security'];
                        unset($options['security']);
                    } else {
                        $security = null;
                    }
                    $transport = \Swift_SmtpTransport::newInstance($host, $port, $security);
                    break;
                default:
                    $transport = \Swift_MailTransport::newInstance();
            }

            foreach ($options as $k => $v) {
                try {
                    $transport->{'set' . ucfirst($k)}($v);
                } catch (\Exception $e) {

                }
            }
        }

        $hash = spl_object_hash($transport);

        if (!isset($this->mailers[$hash])) {
            $this->mailers[$hash] = \Swift_Mailer::newInstance($transport);
        }

        return $this->mailers[$hash]->send($this);
    }
}