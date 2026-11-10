<?php

declare(strict_types=1);

namespace Ai\Agent;

enum RunStatus: string
{
    case Completed = 'completed';
    case Truncated = 'truncated';
    case Blocked = 'blocked';
    case MaxSteps = 'max_steps';
    case Timeout = 'timeout';
}
