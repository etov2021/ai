<?php

declare(strict_types=1);

namespace Ai\Storage;

final class RequestLogRepository
{
    private const TABLE = 'AiRequestLog';

    public function __construct(private DatabaseInterface $db)
    {
    }

    public function add(LogEntry $e): void
    {
        $data = [
            'runId' => $e->runId, 'kind' => $e->kind, 'conversationId' => $e->conversationId, 'agentId' => $e->agentId,
            'userId' => $e->userId, 'providerCode' => $e->providerCode, 'model' => $e->model, 'step' => $e->step,
            'tool' => $e->tool, 'status' => $e->status, 'finishReason' => $e->finishReason,
            'inputTokens' => $e->inputTokens, 'outputTokens' => $e->outputTokens, 'cost' => $e->cost,
            'durationMs' => $e->durationMs, 'error' => $e->error, 'created' => date('Y-m-d H:i:s'),
        ];
        $this->db->execute(Sql::insert(self::TABLE, array_keys($data)), array_values($data));
    }

    /** @return list<array<string, mixed>> */
    public function forRun(string $runId): array
    {
        return $this->db->fetchAll('SELECT * FROM `AiRequestLog` WHERE `runId` = ? ORDER BY `id`', [$runId]);
    }

    /** @return list<array<string, mixed>> */
    public function recent(int $limit = 50): array
    {
        return $this->db->fetchAll('SELECT * FROM `AiRequestLog` ORDER BY `id` DESC LIMIT ' . max(1, min(500, $limit)));
    }
}
