<?php

declare(strict_types=1);

namespace Ai\Tests\Storage;

use Ai\Exception\AiException;
use Ai\Http\CurlClient;
use Ai\Provider\OpenAi;
use Ai\Registry\ProviderRegistry;
use Ai\Security\KeyVault;
use Ai\Security\Policy;
use Ai\Storage\ModelRepository;
use Ai\Storage\ProviderRepository;
use Ai\Tests\SqliteDatabase;
use PHPUnit\Framework\TestCase;

final class RepositoriesTest extends TestCase
{
    private SqliteDatabase $db;
    private ProviderRepository $providers;
    private ModelRepository $models;
    private ProviderRegistry $registry;

    protected function setUp(): void
    {
        $this->db = new SqliteDatabase();
        $policy = new Policy(true, static fn (string $h): array => $h === 'api.example.com' ? ['93.184.216.34'] : []);
        $this->registry = new ProviderRegistry(new CurlClient());
        $this->providers = new ProviderRepository($this->db, new KeyVault(str_repeat('m', 32)), $policy, $this->registry);
        $this->models = new ModelRepository($this->db, $policy);
    }

    public function testApiKeyIsStoredEncryptedAndNeverExposedInConfig(): void
    {
        $config = $this->providers->save('openai', 'OpenAI', 'openai', null, 60, true, 'sk-plain-secret-1234');

        $this->assertTrue($config->hasApiKey);
        $this->assertSame('…1234', $config->apiKeyHint);
        $this->assertStringNotContainsString('sk-plain-secret', print_r($config, true));

        $stored = $this->db->raw('SELECT apiKeyEnc FROM AiProviders')[0]['apiKeyEnc'];
        $this->assertStringNotContainsString('sk-plain-secret', $stored);
        $this->assertSame('sk-plain-secret-1234', $this->providers->apiKey('openai'));
    }

    public function testUpdateWithoutKeyKeepsExistingKeyAndNewKeyReplacesIt(): void
    {
        $this->providers->save('openai', 'OpenAI', 'openai', null, 60, true, 'sk-first-key-aaaa');
        $updated = $this->providers->save('openai', 'OpenAI EU', 'openai', 'https://api.example.com/v1', 30, false);

        $this->assertSame('OpenAI EU', $updated->name);
        $this->assertSame(30, $updated->timeoutSeconds);
        $this->assertFalse($updated->active);
        $this->assertSame('sk-first-key-aaaa', $this->providers->apiKey('openai'));

        $this->providers->save('openai', 'OpenAI EU', 'openai', null, 30, true, 'sk-second-key-bbbb');
        $this->assertSame('sk-second-key-bbbb', $this->providers->apiKey('openai'));
        $this->assertCount(1, $this->providers->all());
    }

    public function testCiphertextCannotBeMovedToAnotherProvider(): void
    {
        $this->providers->save('first', 'First', 'openai', null, 60, true, 'sk-first-key-aaaa');
        $this->providers->save('second', 'Second', 'openai', null, 60, true, 'sk-second-key-bbbb');
        $this->db->execute('UPDATE AiProviders SET apiKeyEnc = (SELECT apiKeyEnc FROM AiProviders WHERE code = ?) WHERE code = ?', ['first', 'second']);

        $this->expectException(AiException::class);
        $this->providers->apiKey('second');
    }

    public function testValidation(): void
    {
        foreach ([
            fn () => $this->providers->save('Bad Code', 'N', 'openai', null, 60, true, 'sk-key-123456'),
            fn () => $this->providers->save('ok', 'N', 'unknown', null, 60, true, 'sk-key-123456'),
            fn () => $this->providers->save('ok', 'N', 'openai', null, 60, true),
            fn () => $this->providers->save('ok', 'N', 'openai', 'http://169.254.169.254', 60, true, 'sk-key-123456'),
            fn () => $this->providers->save('ok', 'N', 'openai', null, 0, true, 'sk-key-123456'),
            fn () => $this->providers->save('ok', 'N', 'openai_compatible', null, 60, true),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('Очаква се изключение.');
            } catch (AiException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame([], $this->providers->all());
    }

    public function testCompatibleProviderNeedsNoKey(): void
    {
        $config = $this->providers->save('ollama', 'Ollama', 'openai_compatible', 'http://localhost:11434/v1', 120, true);

        $this->assertFalse($config->hasApiKey);
        $this->assertNull($this->providers->apiKey('ollama'));
        $this->assertSame('ollama', $this->registry->create($config, '')->name());
    }

    public function testRegistryBuildsProviderFromStoredConfigAndAllowsNewTypes(): void
    {
        $config = $this->providers->save('openai', 'OpenAI', 'openai', null, 60, true, 'sk-plain-secret-1234');
        $this->assertInstanceOf(OpenAi::class, $this->registry->create($config, (string) $this->providers->apiKey('openai')));

        $this->registry->register('custom', fn ($c, $key) => new OpenAi(new CurlClient(), $key ?: 'x', providerName: 'custom'), requiresKey: false);
        $custom = $this->providers->save('mine', 'Mine', 'custom', null, 60, true);
        $this->assertSame('custom', $this->registry->create($custom, '')->name());
    }

    public function testClearApiKey(): void
    {
        $this->providers->save('openai', 'OpenAI', 'openai', null, 60, true, 'sk-plain-secret-1234');
        $this->providers->clearApiKey('openai');

        $this->assertNull($this->providers->apiKey('openai'));
        $this->assertFalse($this->providers->findByCode('openai')->hasApiKey);
    }

    public function testModelsUpsertAndFilters(): void
    {
        $provider = $this->providers->save('openai', 'OpenAI', 'openai', null, 60, true, 'sk-plain-secret-1234');

        $model = $this->models->save($provider->id, 'gpt-x', 'GPT X', 2.5, 10.0, 128000, 16000);
        $this->assertSame(2.5, $model->inputPricePerMillion);
        $this->assertTrue($model->supportsTools);

        $this->models->save($provider->id, 'gpt-x', 'GPT X v2', 3.0, 12.0, null, null, false, false);
        $this->models->save($provider->id, 'gpt-y', 'GPT Y', 0.1, 0.4);

        $all = $this->models->forProvider($provider->id);
        $this->assertCount(2, $all);
        $this->assertCount(1, $this->models->forProvider($provider->id, true));

        $changed = $this->models->find($provider->id, 'gpt-x');
        $this->assertSame('GPT X v2', $changed->label);
        $this->assertNull($changed->contextWindow);
        $this->assertFalse($changed->active);
    }

    public function testModelValidation(): void
    {
        $provider = $this->providers->save('openai', 'OpenAI', 'openai', null, 60, true, 'sk-plain-secret-1234');

        foreach ([
            fn () => $this->models->save($provider->id, '../bad', 'L', 1, 1),
            fn () => $this->models->save($provider->id, 'ok', '', 1, 1),
            fn () => $this->models->save($provider->id, 'ok', 'L', -1, 1),
            fn () => $this->models->save($provider->id, 'ok', 'L', 1, 1, 0),
            fn () => $this->models->save(999, 'ok', 'L', 1, 1),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('Очаква се изключение.');
            } catch (AiException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
