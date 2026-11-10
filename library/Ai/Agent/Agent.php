<?php

declare(strict_types=1);

namespace Ai\Agent;

final readonly class Agent
{
    /**
     * @param list<string> $toolCodes
     * @param bool $runnable агентът, моделът и доставчикът са активни едновременно
     * @param ?Agent $escalation същият агент с по-мощен (по-скъп) модел; ползва се само когато основният не се справи
     */
    public function __construct(
        public int $id,
        public string $code,
        public string $name,
        public string $systemPrompt,
        public string $providerCode,
        public string $model,
        public ?float $temperature,
        public ?int $maxTokens,
        public int $maxSteps,
        public int $timeoutSeconds,
        public bool $runnable,
        public array $toolCodes = [],
        public float $inputPricePerMillion = 0.0,
        public float $outputPricePerMillion = 0.0,
        public int $modelId = 0,
        public bool $active = true,
        public ?Agent $escalation = null,
    ) {
    }

    public function withSystemPrompt(string $systemPrompt): self
    {
        return $this->copy(['systemPrompt' => $systemPrompt]);
    }

    public function withEscalation(?Agent $escalation): self
    {
        return $this->copy(['escalation' => $escalation]);
    }

    /** Същият агент върху друг модел; без собствена ескалация, за да няма вериги. */
    public function withModel(int $modelId, string $providerCode, string $model, float $inputPrice, float $outputPrice): self
    {
        return $this->copy([
            'modelId' => $modelId, 'providerCode' => $providerCode, 'model' => $model,
            'inputPricePerMillion' => $inputPrice, 'outputPricePerMillion' => $outputPrice, 'escalation' => null,
        ]);
    }

    /** @param array<string, mixed> $changes */
    private function copy(array $changes): self
    {
        return new self(...[...get_object_vars($this), ...$changes]);
    }
}
