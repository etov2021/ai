<?php

namespace AiAdmin\Extension;

use Ai\Calendar\CalendarEventsTool;
use Ai\Calendar\CalendarNowTool;
use Ai\Calendar\CalendarProposeTool;
use Ai\Contract\ExtensionInterface;
use Ai\Contract\ToolProviderInterface;
use Ai\Extension\ExtensionRegistry;
use AiAdmin\Service\AiServices;

/** Инструменти calendar_now, calendar_events и calendar_propose_event (Google Calendar). На агент се дават през редове в AiTools с type = builtin. */
class CalendarExtension implements ExtensionInterface, ToolProviderInterface
{
    public function name(): string
    {
        return 'calendar';
    }

    public function register(ExtensionRegistry $registry): void
    {
        $registry->addToolProvider($this);
    }

    public function tools(): array
    {
        $services = AiServices::get();
        $client = $services->calendarClient();

        return [new CalendarNowTool($client), new CalendarEventsTool($client), new CalendarProposeTool($client, $services->calendarProposals())];
    }
}
