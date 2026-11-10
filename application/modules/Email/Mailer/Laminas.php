<?php

namespace Email\Mailer;

use Laminas\Mail\Message;
use Laminas\Mime\Mime;
use Laminas\Mime\Part as MimePart;
use Laminas\Mime\Message as MimeMessage;
use Laminas\Mail\Transport\Smtp;
use Laminas\Mail\Transport\SmtpOptions;
use Laminas\Mail\Transport\Sendmail;

class Laminas extends Message implements MailerInterface
{
    protected $mimeMessage;

    public function __construct(array $options = [])
    {
        $this->setDefaults($options);

        $this->mimeMessage = new MimeMessage();
    }

    public function getMimeMessage()
    {
        return $this->mimeMessage;
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

    public function setHtmlBody($html, $charset = 'UTF-8')
    {
        $part = new MimePart($html);
        $part->setType(Mime::TYPE_HTML);
        $part->setCharset($charset);

        $this->mimeMessage->addPart($part);

        return $this;
    }

    public function setTextBody($text, $charset = 'UTF-8')
    {
        $part = new MimePart($text);
        $part->setType(Mime::TYPE_TEXT);
        $part->setCharset($charset);

        $this->mimeMessage->addPart($part);

        return $this;
    }

    public function attach($file, $contentType = null)
    {
        $pathinfo        = pathinfo($file);
        $at              = new MimePart(file_get_contents($file));
        $at->disposition = Mime::DISPOSITION_ATTACHMENT;
        $at->encoding    = Mime::ENCODING_BASE64;
        $at->filename    = $pathinfo['basename'];

        $this->mimeMessage->addPart($at);

        return $this;
    }

    public function send($transport = null)
    {
        if ($this->mimeMessage !== null) {
            $this->setBody($this->mimeMessage);
        }

        if ($transport === null) {

            $type    = config('mail.laminas_transport.type', []);
            $options = config('mail.laminas_transport.options', []);

            switch ($type) {
                case 'smtp' :
                    $transport = new Smtp(new SmtpOptions($options));
                    break;
                default:
                    $transport = new Sendmail($options);
            }
        }

        return $transport->send($this);
    }
}