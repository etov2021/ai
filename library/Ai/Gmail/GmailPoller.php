<?php

declare(strict_types=1);

namespace Ai\Gmail;

use Ai\Agent\AgentService;
use Ai\Agent\RunStatus;
use Ai\Exception\AiException;
use Ai\Exception\LimitException;

/** Взима нови писма от Gmail, записва ги като известия и (по избор) ги анализира с агент. Вика се от cron. */
final class GmailPoller
{
    private const MAX_ATTEMPTS = 3;

    public function __construct(
        private GmailClient $client,
        private MailRepository $mail,
        private ?AgentService $agents,
        private string $agentCode,
        private string $query,
        private int $limit = 20,
        private int $analyzePerRun = 5,
    ) {
    }

    /** @return array{found: int, added: int, analyzed: int, failed: int, stopped: ?string} */
    public function run(): array
    {
        $result = ['found' => 0, 'added' => 0, 'analyzed' => 0, 'failed' => 0, 'stopped' => null];

        $messages = $this->client->search($this->query, min(max($this->limit, 1), 50));
        $result['found'] = count($messages);

        foreach ($messages as $m) {
            if (!$this->mail->has($m['id'])) {
                $this->mail->add($m['id'], $m['from'], $m['subject'], $m['snippet'], self::date($m['date']));
                $result['added']++;
            }
        }

        // Без агент остава само известие за новите писма.
        if ($this->agents === null || $this->agentCode === '') {
            return $result;
        }

        foreach ($this->mail->pending(self::MAX_ATTEMPTS, $this->analyzePerRun) as $row) {
            try {
                $message = $this->client->read((string) $row['gmailId']);
                $run = $this->agents->run($this->agentCode, $this->prompt($message));
                if ($run->status !== RunStatus::Completed || $run->content === null || trim($run->content) === '') {
                    throw new AiException('Агентът не завърши.');
                }
                $this->mail->setAnalysis((int) $row['id'], trim($run->content));
                $result['analyzed']++;
            } catch (LimitException $e) {
                // Лимит или бюджет: следващите заявки биха се отказали по същата причина.
                $result['stopped'] = $e->getMessage();
                break;
            } catch (AiException) {
                $this->mail->failed((int) $row['id']);
                $result['failed']++;
            }
        }

        return $result;
    }

    /** @param array{from: string, to: string, subject: string, date: string, body: string, truncated: bool} $message */
    private function prompt(array $message): string
    {
        return implode("\n", [
            GmailReadTool::NOTICE,
            'От: ' . $message['from'],
            'Тема: ' . $message['subject'],
            'Дата: ' . $message['date'],
            '',
            $message['body'] === '' ? '[писмото няма текстово съдържание]' : $message['body'],
        ]);
    }

    private static function date(string $header): string
    {
        $time = $header === '' ? false : strtotime($header);

        return date('Y-m-d H:i:s', $time === false ? time() : $time);
    }
}
