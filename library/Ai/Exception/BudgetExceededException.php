<?php

declare(strict_types=1);

namespace Ai\Exception;

final class BudgetExceededException extends LimitException
{
    public function __construct(string $reason)
    {
        parent::__construct('Бюджетът за AI е изчерпан. Опитайте по-късно.', $reason);
    }
}
