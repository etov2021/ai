<?php

declare(strict_types=1);

namespace Ai\Storage;

/** Мост към адаптера на Micro\Database (app('db')); библиотеката не зависи от Micro на ниво клас. */
final class MicroDatabase implements DatabaseInterface
{
    public function __construct(private object $adapter)
    {
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        return array_values($this->adapter->fetchAll($sql, $params));
    }

    public function fetchRow(string $sql, array $params = []): ?array
    {
        $row = $this->adapter->fetchRow($sql, $params);

        return $row ? (array) $row : null;
    }

    public function execute(string $sql, array $params = []): int
    {
        return (int) $this->adapter->query($sql, $params)->rowCount();
    }

    public function lastInsertId(): int
    {
        return (int) $this->adapter->lastInsertId();
    }
}
