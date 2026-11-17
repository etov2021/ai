<?php

declare(strict_types=1);

namespace Ai\Knowledge;

use Ai\Contract\HttpClientInterface;
use Ai\Exception\AiException;
use Ai\Exception\HttpException;
use Ai\Exception\ProviderException;
use Ai\Security\Policy;

/** Превръща текст във вектор чрез Gemini embeddings API. Векторите са нормализирани, затова сходството е скаларно произведение. */
final class GeminiEmbedder
{
    public const DEFAULT_URL = 'https://generativelanguage.googleapis.com/v1beta';
    public const BATCH = 100;

    /** @param \Closure(): string $apiKey взима се чак при първа заявка; хвърля AiException, ако ключ няма */
    public function __construct(
        private HttpClientInterface $http,
        private Policy $policy,
        private \Closure $apiKey,
        private string $model = 'gemini-embedding-001',
        private int $dimensions = 768,
        private ?string $baseUrl = null,
        private int $timeout = 30,
    ) {
    }

    public function model(): string
    {
        return $this->model;
    }

    /** @return list<float> */
    public function embedQuery(string $text): array
    {
        return $this->embed([$text], 'RETRIEVAL_QUERY')[0];
    }

    /**
     * @param list<string> $texts
     * @return list<list<float>> по един вектор за всеки текст, в същия ред
     */
    public function embedDocuments(array $texts): array
    {
        $vectors = [];
        foreach (array_chunk($texts, self::BATCH) as $batch) {
            array_push($vectors, ...$this->embed($batch, 'RETRIEVAL_DOCUMENT'));
        }

        return $vectors;
    }

    /** @return list<list<float>> */
    private function embed(array $texts, string $taskType): array
    {
        $root = rtrim($this->baseUrl ?? self::DEFAULT_URL, '/');
        $this->policy->assertBaseUrl($root);

        $requests = [];
        foreach ($texts as $text) {
            $requests[] = [
                'model' => 'models/' . $this->model,
                'content' => ['parts' => [['text' => $text]]],
                'taskType' => $taskType,
                'outputDimensionality' => $this->dimensions,
            ];
        }

        try {
            $response = $this->http->post(
                $root . '/models/' . rawurlencode($this->model) . ':batchEmbedContents',
                ['x-goog-api-key' => ($this->apiKey)(), 'Content-Type' => 'application/json', 'Accept' => 'application/json'],
                json_encode(['requests' => $requests], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                $this->timeout,
            );
        } catch (HttpException) {
            throw new ProviderException('Gemini не отговаря.');
        }

        $data = json_decode($response->body, true);
        if ($response->status !== 200 || !is_array($data) || count((array) ($data['embeddings'] ?? [])) !== count($texts)) {
            $detail = is_array($data) && is_string($data['error']['message'] ?? null) ? ': ' . mb_substr($data['error']['message'], 0, 200) : '';
            throw new ProviderException(sprintf('Gemini embeddings върна грешка (HTTP %d)%s.', $response->status, $detail), $response->status);
        }

        $vectors = [];
        foreach ($data['embeddings'] as $item) {
            $values = array_map('floatval', (array) ($item['values'] ?? []));
            if ($values === []) {
                throw new AiException('Gemini embeddings върна празен вектор.');
            }
            $vectors[] = self::normalize($values);
        }

        return $vectors;
    }

    /** Вектори с намалена размерност не идват нормализирани. */
    private static function normalize(array $values): array
    {
        $norm = sqrt(array_sum(array_map(static fn (float $v): float => $v * $v, $values)));

        return $norm > 0 ? array_map(static fn (float $v): float => $v / $norm, $values) : $values;
    }
}
