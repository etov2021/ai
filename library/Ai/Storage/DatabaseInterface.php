<?php

declare(strict_types=1);

namespace Ai\Storage;

/** Минимален достъп до базата; параметрите са позиционни (?). */
interface DatabaseInterface
{
    /** @return list<array<string, mixed>> */
    public function fetchAll(string $sql, array $params = []): array;

    /** @return ?array<string, mixed> */
    public function fetchRow(string $sql, array $params = []): ?array;

    /** @return int брой засегнати редове */
    public function execute(string $sql, array $params = []): int;

    public function lastInsertId(): int;
}
