<?php

declare(strict_types=1);

namespace Ai\Storage;

/** Строи INSERT/UPDATE само от фиксирани имена на колони; стойностите винаги са параметри. */
final class Sql
{
    /** @param list<string> $columns */
    public static function insert(string $table, array $columns): string
    {
        return sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            self::ident($table),
            implode(', ', array_map(self::ident(...), $columns)),
            implode(', ', array_fill(0, count($columns), '?')),
        );
    }

    /** @param list<string> $columns */
    public static function update(string $table, array $columns, string $whereColumn): string
    {
        return sprintf(
            'UPDATE %s SET %s WHERE %s = ?',
            self::ident($table),
            implode(', ', array_map(static fn (string $c): string => self::ident($c) . ' = ?', $columns)),
            self::ident($whereColumn),
        );
    }

    private static function ident(string $name): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) !== 1) {
            throw new \InvalidArgumentException('Невалидно име на колона или таблица.');
        }

        return '`' . $name . '`';
    }
}
