<?php

declare(strict_types=1);

namespace Ai\Calendar;

use Ai\Contract\ToolInterface;
use Ai\Message\ToolDefinition;

/** Текущата дата, час и ден от седмицата: моделът не ги знае, а са нужни за „утре“, „следващия понеделник“. */
final class CalendarNowTool implements ToolInterface
{
    public const NAME = 'calendar_now';
    private const DAYS = ['неделя', 'понеделник', 'вторник', 'сряда', 'четвъртък', 'петък', 'събота'];

    public function __construct(private CalendarClient $client)
    {
    }

    public function definition(): ToolDefinition
    {
        return new ToolDefinition(self::NAME, 'Връща текущата дата, час и ден от седмицата. Извикай я първо, когато потребителят говори за "днес", "утре", "тази седмица" и др.', ['type' => 'object', 'properties' => []]);
    }

    public function execute(array $arguments): string
    {
        $now = $this->client->now();

        return sprintf('Сега е %s (%s), часова зона %s.', $now->format('Y-m-d H:i'), self::DAYS[(int) $now->format('w')], $this->client->timezone());
    }
}
