<?php

namespace Email\Mailer;

use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\SendmailTransport;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class Symfony implements MailerInterface
{
    protected Email $email;

    public function __construct(array $options = [])
    {
        $this->email = new Email();

        $this->setDefaults($options);
    }

    public function getEmail(): Email
    {
        return $this->email;
    }

    public function setDefaults(array $options = [])
    {
        $options = $options + config('mail.message', []);

        foreach ($options as $k => $v) {

            $method = 'set' . ucfirst($k);

            if (method_exists($this, $method)) {
                $this->$method($v);
            }
        }
    }

    public function setFrom($emailOrAddressOrList, $name = null)
    {
        $this->email->from(...$this->addresses($emailOrAddressOrList, $name));

        return $this;
    }

    public function addFrom($emailOrAddressOrList, $name = null)
    {
        $this->email->addFrom(...$this->addresses($emailOrAddressOrList, $name));

        return $this;
    }

    public function setReplyTo($emailOrAddressOrList, $name = null)
    {
        $this->email->replyTo(...$this->addresses($emailOrAddressOrList, $name));

        return $this;
    }

    public function addTo($emailOrAddressOrList, $name = null)
    {
        $this->email->addTo(...$this->addresses($emailOrAddressOrList, $name));

        return $this;
    }

    public function addCc($emailOrAddressOrList, $name = null)
    {
        $this->email->addCc(...$this->addresses($emailOrAddressOrList, $name));

        return $this;
    }

    public function addBcc($emailOrAddressOrList, $name = null)
    {
        $this->email->addBcc(...$this->addresses($emailOrAddressOrList, $name));

        return $this;
    }

    public function setSubject($subject)
    {
        $this->email->subject((string) $subject);

        return $this;
    }

    public function setHtmlBody($html, $charset = 'UTF-8')
    {
        $this->email->html($html, $charset);

        return $this;
    }

    public function setTextBody($text, $charset = 'UTF-8')
    {
        $this->email->text($text, $charset);

        return $this;
    }

    public function attach($file, $contentType = null)
    {
        $this->email->attachFromPath($file, null, $contentType);

        return $this;
    }

    public function send($transport = null)
    {
        $transport = $transport ?? $this->createTransport();

        (new Mailer($transport))->send($this->email);

        return $this;
    }

    protected function createTransport(): TransportInterface
    {
        $type    = config('mail.symfony_transport.type', 'smtp');
        $options = config('mail.symfony_transport.options', []);

        if (!empty($options['dsn'])) {
            return Transport::fromDsn($options['dsn']);
        }

        if ($type !== 'smtp') {
            return new SendmailTransport();
        }

        $security  = $options['security'] ?? null;
        $transport = new EsmtpTransport($options['host'] ?? 'localhost', (int) ($options['port'] ?? 25), $security === 'ssl' ? true : null);

        // 'tls' = STARTTLS; без изискването Symfony би продължил и без шифроване, ако сървърът не го предлага.
        if ($security === 'tls') {
            $transport->setRequireTls(true);
        }

        if (isset($options['username'])) {
            $transport->setUsername($options['username']);
        }

        if (isset($options['password'])) {
            $transport->setPassword($options['password']);
        }

        return $transport;
    }

    /**
     * Приема "email", Address, ['email' => 'Име'] и списък от тях.
     *
     * @return Address[]
     */
    protected function addresses($emailOrAddressOrList, $name = null): array
    {
        if ($emailOrAddressOrList instanceof Address) {
            return [$emailOrAddressOrList];
        }

        if (is_array($emailOrAddressOrList)) {

            $addresses = [];

            foreach ($emailOrAddressOrList as $key => $value) {
                if ($value instanceof Address) {
                    $addresses[] = $value;
                } elseif (is_int($key)) {
                    $addresses[] = Address::create((string) $value);
                } else {
                    $addresses[] = new Address((string) $key, (string) $value);
                }
            }

            return $addresses;
        }

        return [$name !== null ? new Address((string) $emailOrAddressOrList, (string) $name) : Address::create((string) $emailOrAddressOrList)];
    }
}
