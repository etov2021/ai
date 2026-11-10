<?php

declare(strict_types=1);

namespace Ai\Tool;

/** Валидира аргументи на инструмент спрямо подмножество на JSON Schema; неизброени свойства се отхвърлят. */
final class SchemaValidator
{
    private const MAX_DEPTH = 8;

    /**
     * @param array<string, mixed> $schema
     * @return list<string> грешки; празен списък означава валидни данни
     */
    public static function validate(array $schema, mixed $value, string $path = 'аргументи', int $depth = 0): array
    {
        if ($depth > self::MAX_DEPTH) {
            return [$path . ': прекалено дълбока структура'];
        }

        $errors = [];
        $types = isset($schema['type']) ? (array) $schema['type'] : [];

        if ($types !== [] && !self::matchesAny($types, $value)) {
            return [sprintf('%s: очакван тип %s', $path, implode('|', $types))];
        }
        if (isset($schema['enum']) && !in_array($value, $schema['enum'], true)) {
            $errors[] = sprintf('%s: стойността не е сред разрешените', $path);
        }

        if (is_string($value)) {
            if (isset($schema['minLength']) && mb_strlen($value) < $schema['minLength']) {
                $errors[] = sprintf('%s: твърде кратка стойност', $path);
            }
            if (isset($schema['maxLength']) && mb_strlen($value) > $schema['maxLength']) {
                $errors[] = sprintf('%s: твърде дълга стойност', $path);
            }
        }

        if ((is_int($value) || is_float($value))) {
            if (isset($schema['minimum']) && $value < $schema['minimum']) {
                $errors[] = sprintf('%s: под минимума %s', $path, $schema['minimum']);
            }
            if (isset($schema['maximum']) && $value > $schema['maximum']) {
                $errors[] = sprintf('%s: над максимума %s', $path, $schema['maximum']);
            }
        }

        if (is_array($value) && array_is_list($value) && $value !== []) {
            if (isset($schema['minItems']) && count($value) < $schema['minItems']) {
                $errors[] = sprintf('%s: твърде малко елементи', $path);
            }
            if (isset($schema['maxItems']) && count($value) > $schema['maxItems']) {
                $errors[] = sprintf('%s: твърде много елементи', $path);
            }
            if (isset($schema['items']) && is_array($schema['items'])) {
                foreach ($value as $i => $item) {
                    array_push($errors, ...self::validate($schema['items'], $item, $path . '[' . $i . ']', $depth + 1));
                }
            }
        } elseif (is_array($value) && (isset($schema['properties']) || in_array('object', $types, true))) {
            $properties = (array) ($schema['properties'] ?? []);

            foreach ($schema['required'] ?? [] as $name) {
                if (!array_key_exists($name, $value)) {
                    $errors[] = sprintf('%s: липсва задължителното поле "%s"', $path, $name);
                }
            }
            foreach ($value as $name => $item) {
                if (!isset($properties[$name])) {
                    // Непознатите полета се отхвърлят, освен ако схемата изрично ги позволява.
                    if (($schema['additionalProperties'] ?? false) !== true) {
                        $errors[] = sprintf('%s: непознато поле "%s"', $path, $name);
                    }
                    continue;
                }
                array_push($errors, ...self::validate((array) $properties[$name], $item, $path . '.' . $name, $depth + 1));
            }
        }

        return $errors;
    }

    /** @param list<string> $types */
    private static function matchesAny(array $types, mixed $value): bool
    {
        foreach ($types as $type) {
            $ok = match ($type) {
                'string' => is_string($value),
                'integer' => is_int($value) || (is_float($value) && floor($value) === $value),
                'number' => is_int($value) || is_float($value),
                'boolean' => is_bool($value),
                'null' => $value === null,
                'array' => is_array($value) && array_is_list($value),
                'object' => is_array($value) && ($value === [] || !array_is_list($value)),
                default => false,
            };
            if ($ok) {
                return true;
            }
        }

        return false;
    }
}
