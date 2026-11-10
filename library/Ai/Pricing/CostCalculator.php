<?php

declare(strict_types=1);

namespace Ai\Pricing;

use Ai\Message\Usage;

final class CostCalculator
{
    /** Цените са в EUR за 1 милион токена (таблица AiModels); резултатът е закръглен до 6 знака като колоната в базата. */
    public static function cost(Usage $usage, float $inputPricePerMillion, float $outputPricePerMillion): float
    {
        return round(
            $usage->inputTokens / 1_000_000 * $inputPricePerMillion + $usage->outputTokens / 1_000_000 * $outputPricePerMillion,
            6,
        );
    }
}
