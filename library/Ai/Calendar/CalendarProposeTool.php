<?php

declare(strict_types=1);

namespace Ai\Calendar;

use Ai\Contract\ToolInterface;
use Ai\Exception\AiException;
use Ai\Exception\ToolException;
use Ai\Message\ToolDefinition;

/** Предлага ново събитие. Не създава нищо в календара: чака потвърждение от потребител в AI - Календар. */
final class CalendarProposeTool implements ToolInterface
{
    public const NAME = 'calendar_propose_event';
    private const MAX_DAYS = 7;

    public function __construct(private CalendarClient $client, private CalendarRepository $proposals)
    {
    }

    public function definition(): ToolDefinition
    {
        return new ToolDefinition(
            self::NAME,
            'Предлага ново събитие в календара. Събитието НЕ се създава веднага: потребителят го потвърждава или отхвърля в AI - Календар. Преди да предложиш, провери за конфликти с calendar_events. След извикването кажи на потребителя, че предложението чака потвърждение.',
            [
                'type' => 'object',
                'properties' => [
                    'summary' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 200, 'description' => 'заглавие'],
                    'start' => ['type' => 'string', 'maxLength' => 25, 'description' => 'начало: ГГГГ-ММ-ДД ЧЧ:ММ'],
                    'end' => ['type' => 'string', 'maxLength' => 25, 'description' => 'край: ГГГГ-ММ-ДД ЧЧ:ММ'],
                    'description' => ['type' => 'string', 'maxLength' => 2000, 'description' => 'описание (по избор)'],
                    'location' => ['type' => 'string', 'maxLength' => 200, 'description' => 'място (по избор)'],
                ],
                'required' => ['summary', 'start', 'end'],
            ],
        );
    }

    public function execute(array $arguments): string
    {
        $start = $this->client->parse((string) $arguments['start']);
        $end = $this->client->parse((string) $arguments['end']);

        if ($end <= $start) {
            throw new ToolException('Краят трябва да е след началото.');
        }
        if ($end > $start->modify('+' . self::MAX_DAYS . ' days')) {
            throw new ToolException(sprintf('Събитието е най-много %d дни.', self::MAX_DAYS));
        }
        if ($start < $this->client->now()->modify('-1 day')) {
            throw new ToolException('Началото е в миналото.');
        }

        try {
            $id = $this->proposals->add(
                trim((string) $arguments['summary']),
                isset($arguments['description']) ? trim((string) $arguments['description']) : null,
                isset($arguments['location']) ? trim((string) $arguments['location']) : null,
                $start,
                $end,
                $this->client->timezone(),
            );
        } catch (AiException $e) {
            throw new ToolException($e->getMessage());
        } catch (\Throwable) {
            throw new ToolException('Предложението не може да бъде записано.');
        }

        return sprintf('Предложение #%d е записано: "%s", %s - %s. Събитието още не е създадено; чака потвърждение от потребителя в AI - Календар.', $id, trim((string) $arguments['summary']), $start->format('Y-m-d H:i'), $end->format('Y-m-d H:i'));
    }
}
