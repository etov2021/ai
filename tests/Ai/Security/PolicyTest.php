<?php

declare(strict_types=1);

namespace Ai\Tests\Security;

use Ai\Exception\AiException;
use Ai\Security\Policy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PolicyTest extends TestCase
{
    private function policy(bool $allowPrivate = false, array $dns = []): Policy
    {
        return new Policy($allowPrivate, static fn (string $host): array => $dns[$host] ?? []);
    }

    public function testPublicHttpsIsAllowed(): void
    {
        $this->policy(dns: ['api.example.com' => ['93.184.216.34']])->assertBaseUrl('https://api.example.com/v1');
        $this->addToAssertionCount(1);
    }

    /** @return iterable<string, array{string, bool, array<string, list<string>>}> */
    public static function rejected(): iterable
    {
        yield 'metadata ip' => ['http://169.254.169.254/latest', true, []];
        yield 'metadata via dns' => ['https://evil.example', true, ['evil.example' => ['169.254.169.254']]];
        yield 'loopback when private denied' => ['https://127.0.0.1', false, []];
        yield 'localhost when private denied' => ['http://localhost:11434', false, []];
        yield 'internal dns when private denied' => ['https://intranet.local', false, ['intranet.local' => ['10.0.0.5']]];
        yield 'http to public host' => ['http://api.example.com', true, ['api.example.com' => ['93.184.216.34']]];
        yield 'userinfo' => ['https://user:pw@api.example.com', false, ['api.example.com' => ['93.184.216.34']]];
        yield 'bad scheme' => ['file:///etc/passwd', true, []];
        yield 'unresolvable' => ['https://nope.example', false, []];
        yield 'mixed public and private' => ['http://mixed.example', true, ['mixed.example' => ['10.0.0.1', '93.184.216.34']]];
    }

    /** @param array<string, list<string>> $dns */
    #[DataProvider('rejected')]
    public function testRejected(string $url, bool $allowPrivate, array $dns): void
    {
        $this->expectException(AiException::class);
        $this->policy($allowPrivate, $dns)->assertBaseUrl($url);
    }

    public function testLocalServerAllowedOnlyWhenEnabled(): void
    {
        $this->policy(true)->assertBaseUrl('http://localhost:11434/v1');
        $this->policy(true)->assertBaseUrl('http://192.168.1.10:8080');
        $this->addToAssertionCount(2);
    }

    public function testCodeAndModelValidation(): void
    {
        $policy = $this->policy();
        $policy->assertCode('openai-eu');
        $policy->assertModelId('models/gemini-2.0:flash');
        $this->addToAssertionCount(2);

        foreach ([fn () => $policy->assertCode('Bad Code'), fn () => $policy->assertModelId('../x'), fn () => $policy->assertModelId('a b')] as $bad) {
            try {
                $bad();
                $this->fail('Очаква се изключение.');
            } catch (AiException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
