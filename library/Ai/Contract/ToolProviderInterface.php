<?php

declare(strict_types=1);

namespace Ai\Contract;

/** Източник на инструменти, напр. MCP клиент, който открива инструментите на отдалечен сървър. */
interface ToolProviderInterface
{
    /** @return list<ToolInterface> */
    public function tools(): array;
}
