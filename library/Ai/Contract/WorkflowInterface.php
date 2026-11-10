<?php

declare(strict_types=1);

namespace Ai\Contract;

/** Именувана автоматизация от няколко стъпки (агенти, инструменти, условия). Библиотеката само я регистрира. */
interface WorkflowInterface
{
    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function run(array $input): array;
}
