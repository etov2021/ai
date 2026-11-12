<?php

namespace Email\Model;

use Email\Mailer as EmailMailer;
use Email\Model\Table\EmailBcc;

class Mailer
{
    protected $mailer;
    protected $allowed = [];
    protected $domains = [];
    protected $cc = [];
    protected $bcc = [];
    protected $cli = false;

    protected $attachments = [];

    public function __construct($mailer = null, $cli = false)
    {
        if ($mailer === null) {
            if (($mailer = config('mail.mailer')) === null) {
                throw new \Exception(__METHOD__ . ' :: Please set mail.mailer in config', 500);
            }
        }

        $this->cli = $cli;
        $this->mailer = new $mailer();
        $this->allowed = (new EmailBcc())->fetch();
        $this->domains = (new \Domains\Model\Domains())->fetchCachedPairs(null,['id',expr("CONCAT(`protocol`,'://',`name`,'/')")]);

    }

    public function setMailer($mailer)
    {
        $this->mailer = $mailer;

        return $this;
    }

    public function attach($file, $contentType = null)
    {
        $this->attachments[] = [$file, $contentType];

        return $this;
    }

    public function setAttachments($attachments)
    {
        $this->attachments = $attachments;

        return $this;
    }

    public function clearAttachments()
    {
        $this->attachments = [];

        return $this;
    }

    public function send($alias, $to, array $data = [], $from = null, $language = null, $options = [])
    {
        $domainId = isset($options['domainId']) ? $options['domainId'] : (defined('DOMAIN') ? DOMAIN : 1);

        return $this->sendDirect($alias, $to, $data, $from, $language, $domainId);
    }

    /**
     *
     * @param unknown $alias
     * @param unknown $to
     * @param array $data
     * @param unknown $from
     * @param unknown $language
     * @throws \Exception
     * @throws Exception
     * @return \Email\Model\Mailer
     */
    public function sendDirect($alias, $to, array $data = [], $from = null, $language = null, $domainId = null)
    {
        if (!config('mail.enable', 1)) {
            return $this;
        }

        $domainId = $domainId !== null ? $domainId : (defined('DOMAIN') ? DOMAIN : 1);

        $template = (new Templates())->getTemplate($alias, $language, $domainId);

        if ($template === null) {
            throw new \Exception('Cannot find template with alias/id: ' . $alias, 404);
        }

        if (!$template->getActive()) {
            return $this;
        }

        $template->setDescription(
            $this->replaceTemplateContent($template->getDescription(), $data)
        );

        $template->setSubject(
            $this->replaceTemplateContent($template->getSubject(), $data)
        );

        if ($this->cli && isset($this->domains[$domainId])) {
            $preffix = $this->domains[$domainId];
        } else {
            $preffix = server_url(base_url()) . '/';
        }

        $content = preg_replace('#background\: url\((.*?)\)#', 'background: url('. $preffix .'$1)', $template->getDescription());
        $content = preg_replace('#\<img src=\"(.*?)\"#', '<img src="'. $preffix .'$1"', $content);

        $template->setDescription($content);

        $mailer = $this->mailer;

        $mailer->setSubject($template->getSubject());

        if ($from) {
            $mailer->setFrom($from);
        } else {
            $mailFrom = settings('mailFrom', null, $domainId);
            $mailFromName = settings('mailFromName', null, $domainId);
            if ($mailFrom && $mailFromName)  {
                $mailer->setFrom([$mailFrom => $mailFromName]);
                $mailer->setReplyTo($mailFrom);
            } else if ($mailFrom) {
                $mailer->setFrom($mailFrom);
                $mailer->setReplyTo($mailFrom);
            }
        }

        if (!env('production') && config('mail.testEmails')) {
           $to = config('mail.testEmails');
        }

        if (is_array($to)) {
            foreach ($to as $t) {
                $mailer->addTo($t);
            }
        } else {
            $mailer->addTo($to);
        }

        $templateDescription = $template->getDescription();

        $content = '<html><head><title>' . $template->getSubject() . '</title></head><body>' . $template->getDescription() . '</body></html>';

        if ($mailer instanceof EmailMailer\Symfony) {
            $mailer->setHtmlBody($content);
            //$mail->setTextBody(strip_tags($templateDescription));
        } elseif ($mailer instanceof EmailMailer\Swift) {
            $mailer->setBody($content, 'text/html');
            //$mail->addPart(strip_tags($templateDescription), 'text/plain');
        }

        foreach ($this->attachments as $attachment) {
            if ($mailer instanceof EmailMailer\Symfony) {
                $mailer->attach($attachment[0], $attachment[1]);
            } elseif ($mailer instanceof EmailMailer\Swift) {
                $mailer->attach(\Swift_Attachment::fromPath($attachment[0], $attachment[1]));
            }
        }

        $mailer->send();

        return $this;
    }
    /**
     *
     * @param unknown $email
     * @param string $name
     * @throws \Exception
     * @return \Email\Model\Mailer
     */
    public function addCc($email)
    {
        if (!is_array($email)) {
            $email = [$email];
        }

        foreach ($email as $n => $recipient) {
            if(in_array($recipient, $this->allowed)) {
                $this->cc[] = $recipient;
                $this->mailer->addCc($recipient);
            } else{
                if(!env('production')) {
                    throw new \Exception('Email '.$recipient.' is not in allowed EmailBcc table');
                }
            }
        }

        return $this;
    }
    /**
     *
     * @param unknown $email
     * @throws \Exception
     * @return \Email\Model\Mailer
     */
    public function addBcc($email)
    {
        if (!is_array($email)) {
            $email = [$email];
        }

        $allowedBccModel = (new EmailBcc());

        foreach ($email as $recipient) {
            if(in_array($recipient, $this->allowed)) {
                if(!in_array($recipient, $this->bcc)) {
                    $this->bcc[] = $recipient;
                    $this->mailer->addBcc($recipient);
                }
            } else{
                if(!env('production')) {
                    throw new \Exception('Email '.$recipient.' is not in allowed EmailBcc table');
                }
            }
        }

        return $this;
    }


    public function replaceTemplateContent($content, $data)
    {
        if (preg_match_all('~\[(\w*)\]~', $content, $matches)) {
            foreach($matches[1] as $m) {
                if (array_key_exists($m, $data)) {
                    $content = preg_replace("~\[{$m}\]~", $data[$m], $content);
                }
            }
        }

        return $content;
    }
}