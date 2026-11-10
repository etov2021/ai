<?php

declare(strict_types=1);

namespace Ai\Tests;

use Ai\Storage\DatabaseInterface;

/** SQLite в паметта със същата структура като миграцията за MySQL. */
class SqliteDatabase implements DatabaseInterface
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = new \PDO('sqlite::memory:', options: [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC]);
        $this->pdo->exec('PRAGMA foreign_keys = ON');
        $this->pdo->exec('CREATE TABLE AiProviders (
            id INTEGER PRIMARY KEY AUTOINCREMENT, code TEXT NOT NULL UNIQUE, name TEXT NOT NULL, type TEXT NOT NULL,
            baseUrl TEXT NULL, apiKeyEnc TEXT NULL, apiKeyHint TEXT NULL, timeoutSeconds INTEGER NOT NULL DEFAULT 60,
            active INTEGER NOT NULL DEFAULT 1, created TEXT NOT NULL, updated TEXT NOT NULL)');
        $this->pdo->exec('CREATE TABLE AiModels (
            id INTEGER PRIMARY KEY AUTOINCREMENT, providerId INTEGER NOT NULL REFERENCES AiProviders(id) ON DELETE CASCADE,
            model TEXT NOT NULL, label TEXT NOT NULL, inputPricePerMillion REAL NOT NULL DEFAULT 0,
            outputPricePerMillion REAL NOT NULL DEFAULT 0, contextWindow INTEGER NULL, maxOutputTokens INTEGER NULL,
            supportsTools INTEGER NOT NULL DEFAULT 1, active INTEGER NOT NULL DEFAULT 1, created TEXT NOT NULL, updated TEXT NOT NULL,
            UNIQUE (providerId, model))');
        $this->pdo->exec('CREATE TABLE AiTools (
            id INTEGER PRIMARY KEY AUTOINCREMENT, code TEXT NOT NULL UNIQUE, name TEXT NOT NULL, description TEXT NOT NULL,
            type TEXT NOT NULL, parameters TEXT NOT NULL, config TEXT NULL, active INTEGER NOT NULL DEFAULT 1,
            created TEXT NOT NULL, updated TEXT NOT NULL)');
        $this->pdo->exec('CREATE TABLE AiAgents (
            id INTEGER PRIMARY KEY AUTOINCREMENT, code TEXT NOT NULL UNIQUE, name TEXT NOT NULL, description TEXT NULL,
            systemPrompt TEXT NOT NULL, modelId INTEGER NOT NULL REFERENCES AiModels(id) ON DELETE RESTRICT,
            escalationModelId INTEGER NULL REFERENCES AiModels(id) ON DELETE RESTRICT,
            temperature REAL NULL, maxTokens INTEGER NULL, maxSteps INTEGER NOT NULL DEFAULT 5, timeoutSeconds INTEGER NOT NULL DEFAULT 120,
            active INTEGER NOT NULL DEFAULT 1, created TEXT NOT NULL, updated TEXT NOT NULL)');
        $this->pdo->exec('CREATE TABLE AiAgentTools (
            agentId INTEGER NOT NULL REFERENCES AiAgents(id) ON DELETE CASCADE,
            toolId INTEGER NOT NULL REFERENCES AiTools(id) ON DELETE CASCADE, PRIMARY KEY (agentId, toolId))');
        $this->pdo->exec('CREATE TABLE AiConversations (
            id INTEGER PRIMARY KEY AUTOINCREMENT, agentId INTEGER NOT NULL REFERENCES AiAgents(id) ON DELETE RESTRICT,
            userId INTEGER NULL, title TEXT NOT NULL DEFAULT \'\', created TEXT NOT NULL, updated TEXT NOT NULL)');
        $this->pdo->exec('CREATE TABLE AiMessages (
            id INTEGER PRIMARY KEY AUTOINCREMENT, conversationId INTEGER NOT NULL REFERENCES AiConversations(id) ON DELETE CASCADE,
            seq INTEGER NOT NULL, role TEXT NOT NULL, content TEXT NULL, toolCalls TEXT NULL, toolCallId TEXT NULL, name TEXT NULL,
            created TEXT NOT NULL, UNIQUE (conversationId, seq))');
        $this->pdo->exec('CREATE TABLE AiRequestLog (
            id INTEGER PRIMARY KEY AUTOINCREMENT, runId TEXT NOT NULL, kind TEXT NOT NULL, conversationId INTEGER NULL,
            agentId INTEGER NULL, userId INTEGER NULL, providerCode TEXT NOT NULL DEFAULT \'\', model TEXT NOT NULL DEFAULT \'\',
            step INTEGER NOT NULL DEFAULT 0, tool TEXT NULL, status TEXT NOT NULL, finishReason TEXT NULL,
            inputTokens INTEGER NOT NULL DEFAULT 0, outputTokens INTEGER NOT NULL DEFAULT 0, cost REAL NOT NULL DEFAULT 0,
            durationMs INTEGER NOT NULL DEFAULT 0, error TEXT NULL, created TEXT NOT NULL)');
        $this->pdo->exec('CREATE TABLE AiBudgets (
            id INTEGER PRIMARY KEY AUTOINCREMENT, scope TEXT NOT NULL, scopeKey TEXT NOT NULL DEFAULT \'\',
            rateLimitPerMinute INTEGER NULL, dailyLimit REAL NULL, monthlyLimit REAL NULL, warnPercent INTEGER NOT NULL DEFAULT 80,
            active INTEGER NOT NULL DEFAULT 1, updated TEXT NOT NULL, UNIQUE (scope, scopeKey))');
        $this->pdo->exec('CREATE TABLE AiUsageDaily (
            day TEXT NOT NULL, scope TEXT NOT NULL, scopeKey TEXT NOT NULL DEFAULT \'\', requests INTEGER NOT NULL DEFAULT 0,
            inputTokens INTEGER NOT NULL DEFAULT 0, outputTokens INTEGER NOT NULL DEFAULT 0, cost REAL NOT NULL DEFAULT 0,
            PRIMARY KEY (day, scope, scopeKey))');
        $this->pdo->exec('CREATE TABLE AiRateLimit (
            bucket TEXT NOT NULL, windowStart INTEGER NOT NULL, hits INTEGER NOT NULL DEFAULT 0, PRIMARY KEY (bucket, windowStart))');
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function fetchRow(string $sql, array $params = []): ?array
    {
        return $this->fetchAll($sql, $params)[0] ?? null;
    }

    public function execute(string $sql, array $params = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    public function lastInsertId(): int
    {
        return (int) $this->pdo->lastInsertId();
    }

    public function raw(string $sql): array
    {
        return $this->fetchAll($sql);
    }
}
