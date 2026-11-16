<?php

declare(strict_types=1);

namespace Ai\Gmail;

use Ai\Agent\AgentService;
use Ai\Agent\RunStatus;
use Ai\Calendar\CalendarClient;
use Ai\Calendar\CalendarRepository;
use Ai\Exception\AiException;
use Ai\Exception\LimitException;

/** Взима нови писма от Gmail, записва ги като известия и (по избор) ги анализира с агент. Вика се от cron. */
final class GmailPoller
{
    private const MAX_ATTEMPTS = 3;
    private const MAX_EVENTS_PER_MAIL = 3;

    public function __construct(
        private GmailClient $client,
        private MailRepository $mail,
        private ?AgentService $agents,
        private string $agentCode,
        private string $query,
        private int $limit = 20,
        private int $analyzePerRun = 5,
        private ?CalendarClient $calendar = null,
        private ?CalendarRepository $proposals = null,
        private string $calendarAgentCode = '',
        private int $calendarPerRun = 5,
    ) {
    }

    /** @return array{found: int, added: int, analyzed: int, failed: int, proposed: int, stopped: ?string} */
    public function run(): array
    {
        $result = ['found' => 0, 'added' => 0, 'analyzed' => 0, 'failed' => 0, 'proposed' => 0, 'stopped' => null];

        $messages = $this->client->search($this->query, min(max($this->limit, 1), 50));
        $result['found'] = count($messages);

        foreach ($messages as $m) {
            if (!$this->mail->has($m['id'])) {
                $this->mail->add($m['id'], $m['from'], $m['subject'], $m['snippet'], self::date($m['date']));
                $result['added']++;
            }
        }

        // Без агент остава само известие за новите писма.
        if ($this->agents !== null && $this->agentCode !== '') {
            $this->analyze($result);
        }
        if ($this->agents !== null && $this->calendarAgentCode !== '' && $this->calendar !== null && $this->proposals !== null && $result['stopped'] === null) {
            $this->extractEvents($result);
        }

        return $result;
    }

    /** @param array<string, mixed> $result */
    private function analyze(array &$result): void
    {
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
    }

    /**
     * Търси в писмата срещи с конкретна дата и час и ги записва като предложения за потвърждение.
     *
     * @param array<string, mixed> $result
     */
    private function extractEvents(array &$result): void
    {
        try {
            $rows = $this->mail->pendingCalendar(self::MAX_ATTEMPTS, $this->calendarPerRun);
        } catch (\Throwable) {
            $result['stopped'] = 'Събитията от писма изискват миграция 012_mail_calendar.sql.';

            return;
        }

        foreach ($rows as $row) {
            try {
                $message = $this->client->read((string) $row['gmailId']);
                $run = $this->agents->run($this->calendarAgentCode, $this->calendarPrompt($message));
                if ($run->status !== RunStatus::Completed || $run->content === null) {
                    throw new AiException('Агентът не завърши.');
                }
                $result['proposed'] += $this->propose((string) $row['gmailId'], $run->content);
                $this->mail->calendarDone((int) $row['id']);
            } catch (LimitException $e) {
                $result['stopped'] = $e->getMessage();
                break;
            } catch (AiException $e) {
                // Пълен списък за потвърждение: писмото остава непрегледано и се опитва пак след като потребителят реши част от предложенията.
                if (str_contains($e->getMessage(), 'непотвърдени предложения')) {
                    break;
                }
                $this->mail->calendarFailed((int) $row['id']);
            }
        }
    }

    /** @return int брой записани предложения */
    private function propose(string $gmailId, string $agentOutput): int
    {
        // Моделите често обграждат JSON с ```; махат се, преди да се чете.
        $json = trim(preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($agentOutput)) ?? '');
        $items = json_decode($json, true);
        if (!is_array($items)) {
            return 0;
        }

        $added = 0;
        $now = $this->calendar->now();

        foreach (array_slice(array_values($items), 0, self::MAX_EVENTS_PER_MAIL) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $summary = trim((string) ($item['summary'] ?? ''));
            try {
                $start = $this->calendar->parse((string) ($item['start'] ?? ''));
                $end = isset($item['end']) && trim((string) $item['end']) !== '' ? $this->calendar->parse((string) $item['end']) : $start->modify('+1 hour');
            } catch (AiException) {
                continue;
            }

            // Същите правила като при предложение от агент: смислен период и не в миналото.
            if ($summary === '' || mb_strlen($summary) > 200 || $end <= $start || $end > $start->modify('+7 days') || $start < $now) {
                continue;
            }

            $source = 'mail:' . $gmailId;
            if ($this->proposals->hasSource($source, $summary, $start)) {
                continue;
            }

            $this->proposals->add(
                $summary,
                'От писмо в Gmail.',
                isset($item['location']) ? mb_substr(trim((string) $item['location']), 0, 200) : null,
                $start,
                $end,
                $this->calendar->timezone(),
                $source,
            );
            $added++;
        }

        return $added;
    }

    /** @param array{from: string, to: string, subject: string, date: string, body: string, truncated: bool} $message */
    private function calendarPrompt(array $message): string
    {
        return implode("\n", [
            sprintf('Сега е %s, часова зона %s.', $this->calendar->now()->format('Y-m-d H:i'), $this->calendar->timezone()),
            'Задача: от писмото по-долу извлечи срещи и събития с конкретна дата и час, на които получателят трябва да присъства.',
            'Върни САМО JSON масив (може да е празен): [{"summary":"заглавие","start":"ГГГГ-ММ-ДД ЧЧ:ММ","end":"ГГГГ-ММ-ДД ЧЧ:ММ","location":"място"}].',
            'Ако няма конкретна дата и час, върни []. Ако няма край, остави го празен. Не измисляй.',
            '',
            $this->prompt($message),
        ]);
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
