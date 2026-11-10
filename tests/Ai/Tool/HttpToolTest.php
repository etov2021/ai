<?php

declare(strict_types=1);

namespace Ai\Tests\Tool;

use Ai\Exception\AiException;
use Ai\Exception\ToolException;
use Ai\Message\ToolDefinition;
use Ai\Security\Policy;
use Ai\Tests\FakeHttpClient;
use Ai\Tool\HttpTool;
use PHPUnit\Framework\TestCase;

final class HttpToolTest extends TestCase
{
    private function tool(FakeHttpClient $http, array $config = []): HttpTool
    {
        $policy = new Policy(false, static fn (string $h): array => $h === 'api.example.com' ? ['93.184.216.34'] : ($h === 'evil.example' ? ['10.0.0.1'] : []));
        $definition = new ToolDefinition('items', 'Артикули', ['type' => 'object', 'properties' => ['id' => ['type' => 'string']]]);

        return new HttpTool($http, $policy, $definition, $config + [
            'method' => 'GET',
            'url' => 'https://api.example.com/items/{id}',
            'allowedHosts' => ['api.example.com'],
        ]);
    }

    public function testGetFillsEncodedPathAndQuery(): void
    {
        $http = new FakeHttpClient(['ok' => true]);

        $result = $this->tool($http)->execute(['id' => 'a/b c', 'lang' => 'bg']);

        $this->assertSame('GET', $http->method);
        $this->assertSame('https://api.example.com/items/a%2Fb%20c?lang=bg', $http->url);
        $this->assertStringStartsWith("HTTP 200\n", $result);
    }

    public function testPostSendsRemainingArgumentsAsJson(): void
    {
        $http = new FakeHttpClient(['ok' => true]);

        $this->tool($http, ['method' => 'POST', 'url' => 'https://api.example.com/items'])->execute(['name' => 'Тест']);

        $this->assertSame('POST', $http->method);
        $this->assertSame(['name' => 'Тест'], $http->payload);
    }

    public function testResultIsTruncated(): void
    {
        $result = $this->tool(new FakeHttpClient(str_repeat('x', 500)), ['maxResultChars' => 100])->execute(['id' => '1']);

        $this->assertStringContainsString('[отговорът е съкратен]', $result);
        $this->assertLessThan(200, mb_strlen($result));
    }

    public function testDotSegmentsAndMissingValuesAreRejected(): void
    {
        foreach ([['id' => '..'], ['id' => '.'], [], ['id' => ['x']]] as $args) {
            try {
                $this->tool(new FakeHttpClient([]))->execute($args);
                $this->fail('Очаква се изключение.');
            } catch (ToolException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testQueryCannotEscapeAllowedHost(): void
    {
        $http = new FakeHttpClient([]);

        $this->tool($http)->execute(['id' => 'https://evil.example/', 'q' => '@evil.example']);

        $this->assertSame('api.example.com', parse_url($http->url, PHP_URL_HOST));
    }

    public function testHostResolvingToInternalAddressIsBlockedAtCallTime(): void
    {
        $policy = new Policy(false, static fn (string $h): array => ['10.0.0.1']);
        $tool = new HttpTool(new FakeHttpClient([]), $policy, new ToolDefinition('t', 'd'), [
            'method' => 'GET', 'url' => 'https://api.example.com/x', 'allowedHosts' => ['api.example.com'],
        ]);

        $this->expectException(ToolException::class);
        $tool->execute([]);
    }

    public function testInvalidConfigIsRejected(): void
    {
        foreach ([
            ['method' => 'DELETE', 'url' => 'https://api.example.com/', 'allowedHosts' => ['api.example.com']],
            ['method' => 'GET', 'url' => 'https://{host}/x', 'allowedHosts' => ['api.example.com']],
            ['method' => 'GET', 'url' => 'https://other.example/x', 'allowedHosts' => ['api.example.com']],
            ['method' => 'GET', 'url' => 'https://api.example.com/x', 'allowedHosts' => []],
            ['method' => 'GET', 'url' => 'https://user@api.example.com/x', 'allowedHosts' => ['api.example.com']],
            ['method' => 'GET', 'url' => 'https://api.example.com/x', 'allowedHosts' => ['api.example.com'], 'timeoutSeconds' => 999],
        ] as $config) {
            try {
                HttpTool::assertConfig($config);
                $this->fail('Очаква се изключение.');
            } catch (AiException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testPortInUrlMatchesHostCheck(): void
    {
        HttpTool::assertConfig(['method' => 'GET', 'url' => 'https://api.example.com:8443/x', 'allowedHosts' => ['api.example.com']]);
        $this->addToAssertionCount(1);
    }
}
