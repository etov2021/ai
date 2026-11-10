<?php

declare(strict_types=1);

namespace Ai\Tests\Tool;

use Ai\Tool\SchemaValidator;
use PHPUnit\Framework\TestCase;

final class SchemaValidatorTest extends TestCase
{
    private const SCHEMA = [
        'type' => 'object',
        'properties' => [
            'city' => ['type' => 'string', 'minLength' => 2, 'maxLength' => 20],
            'days' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 7],
            'unit' => ['type' => 'string', 'enum' => ['c', 'f']],
            'tags' => ['type' => 'array', 'items' => ['type' => 'string'], 'maxItems' => 2],
        ],
        'required' => ['city'],
    ];

    public function testValidArguments(): void
    {
        $this->assertSame([], SchemaValidator::validate(self::SCHEMA, ['city' => 'Русе', 'days' => 3, 'unit' => 'c', 'tags' => ['a']]));
    }

    public function testEmptyObjectAgainstSchemaWithoutRequired(): void
    {
        $this->assertSame([], SchemaValidator::validate(['type' => 'object', 'properties' => []], []));
    }

    public function testReportsProblems(): void
    {
        $cases = [
            'missing required' => [[], 'липсва задължителното'],
            'wrong type' => [['city' => 5], 'очакван тип string'],
            'too short' => [['city' => 'a'], 'твърде кратка'],
            'below minimum' => [['city' => 'Русе', 'days' => 0], 'под минимума'],
            'float for integer' => [['city' => 'Русе', 'days' => 2.5], 'очакван тип integer'],
            'enum' => [['city' => 'Русе', 'unit' => 'k'], 'не е сред разрешените'],
            'unknown field' => [['city' => 'Русе', 'evil' => 1], 'непознато поле'],
            'array item type' => [['city' => 'Русе', 'tags' => [1]], 'очакван тип string'],
            'too many items' => [['city' => 'Русе', 'tags' => ['a', 'b', 'c']], 'твърде много'],
            'list for object' => [['a', 'b'], 'очакван тип object'],
        ];

        foreach ($cases as $label => [$value, $expected]) {
            $errors = SchemaValidator::validate(self::SCHEMA, $value);
            $this->assertNotEmpty($errors, $label);
            $this->assertStringContainsString($expected, implode(' ', $errors), $label);
        }
    }

    public function testIntegralFloatCountsAsInteger(): void
    {
        $this->assertSame([], SchemaValidator::validate(self::SCHEMA, ['city' => 'Русе', 'days' => 3.0]));
    }

    public function testAdditionalPropertiesCanBeAllowedExplicitly(): void
    {
        $schema = ['type' => 'object', 'properties' => [], 'additionalProperties' => true];

        $this->assertSame([], SchemaValidator::validate($schema, ['x' => 1]));
    }

    public function testDeepNestingIsRejected(): void
    {
        $schema = ['type' => 'object', 'properties' => ['a' => ['type' => 'object', 'properties' => []]]];
        $value = [];
        for ($i = 0; $i < 12; $i++) {
            $schema = ['type' => 'object', 'properties' => ['a' => $schema]];
            $value = ['a' => $value];
        }

        $this->assertNotEmpty(SchemaValidator::validate($schema, $value));
    }
}
