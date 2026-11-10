<?php

declare(strict_types=1);

namespace Ai\Tests\Security;

use Ai\Exception\AiException;
use Ai\Security\KeyVault;
use PHPUnit\Framework\TestCase;

final class KeyVaultTest extends TestCase
{
    private function vault(): KeyVault
    {
        return new KeyVault(str_repeat('k', 32));
    }

    public function testRoundTripAndRandomIv(): void
    {
        $a = $this->vault()->encrypt('sk-secret-123', 'provider:openai');
        $b = $this->vault()->encrypt('sk-secret-123', 'provider:openai');

        $this->assertNotSame($a, $b);
        $this->assertStringNotContainsString('sk-secret-123', $a);
        $this->assertSame('sk-secret-123', $this->vault()->decrypt($a, 'provider:openai'));
    }

    public function testWrongContextWrongKeyAndTamperingAreRejected(): void
    {
        $payload = $this->vault()->encrypt('secret', 'provider:a');

        foreach ([
            fn () => $this->vault()->decrypt($payload, 'provider:b'),
            fn () => (new KeyVault(str_repeat('x', 32)))->decrypt($payload, 'provider:a'),
            fn () => $this->vault()->decrypt(substr($payload, 0, -3) . 'AAA', 'provider:a'),
            fn () => $this->vault()->decrypt('garbage', 'provider:a'),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('Очаква се изключение.');
            } catch (AiException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testShortMasterKeyIsRejected(): void
    {
        $this->expectException(AiException::class);
        new KeyVault('short');
    }

    public function testFromEnv(): void
    {
        putenv('AI_TEST_KEY=' . KeyVault::generateMasterKey());
        $vault = KeyVault::fromEnv('AI_TEST_KEY');
        $this->assertSame('x', $vault->decrypt($vault->encrypt('x', 'c'), 'c'));

        putenv('AI_TEST_KEY');
        $this->expectException(AiException::class);
        KeyVault::fromEnv('AI_TEST_KEY');
    }

    public function testHint(): void
    {
        $this->assertSame('…cdef', KeyVault::hint('sk-abcdef'));
        $this->assertSame('****', KeyVault::hint('short'));
    }
}
