<?php

declare(strict_types=1);

namespace Ai\Contract;

use Ai\Agent\Agent;

/** Инструмент, който се настройва според агента, който го вика (напр. достъп само до негови документи). */
interface AgentAwareToolInterface extends ToolInterface
{
    public function forAgent(Agent $agent): ToolInterface;
}
