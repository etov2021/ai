<?php

namespace Email\Mailer;

interface MailerInterface
{
    public function addFrom($emailOrAddressOrList, $name = null);
    public function addTo($emailOrAddressOrList, $name = null);
    public function addCc($emailOrAddressOrList, $name = null);
    public function addBcc($emailOrAddressOrList, $name = null);
    public function setSubject($subject);
    public function setHtmlBody($html, $charset = 'UTF-8');
    public function setTextBody($text, $charset = 'UTF-8');
    public function attach($file, $contentType = null);
    public function send($transport = null);
}