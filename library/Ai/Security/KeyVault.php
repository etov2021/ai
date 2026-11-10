<?php

declare(strict_types=1);

namespace Ai\Security;

use Ai\Exception\AiException;

/**
 * Криптира API ключове преди запис в базата (AES-256-GCM).
 * Главният ключ идва само от околната среда и никога не се записва в базата или в репото.
 */
final class KeyVault
{
    private const VERSION = 'v1';
    private const CIPHER = 'aes-256-gcm';
    private const IV_LENGTH = 12;
    private const TAG_LENGTH = 16;

    private string $key;

    public function __construct(#[\SensitiveParameter] string $masterKey)
    {
        if (strlen($masterKey) < 32) {
            throw new AiException('Главният ключ трябва да е поне 32 байта.');
        }
        // Отделен подключ за тази цел: същият главен ключ може да се ползва и другаде без припокриване.
        $this->key = hash_hkdf('sha256', $masterKey, 32, 'ai-keyvault-' . self::VERSION);
    }

    /** Чете base64 кодиран 32-байтов ключ от променлива на средата (по подразбиране AI_MASTER_KEY). */
    public static function fromEnv(string $variable = 'AI_MASTER_KEY'): self
    {
        $encoded = getenv($variable);
        if ($encoded === false || $encoded === '') {
            throw new AiException(sprintf('Липсва променлива на средата %s.', $variable));
        }
        $raw = base64_decode($encoded, true);
        if ($raw === false) {
            throw new AiException(sprintf('%s не е валиден base64.', $variable));
        }

        return new self($raw);
    }

    /** Нов главен ключ във формат за AI_MASTER_KEY. */
    public static function generateMasterKey(): string
    {
        return base64_encode(random_bytes(32));
    }

    /** Кратка маска за показване в админа; не е достатъчна за възстановяване на ключа. */
    public static function hint(#[\SensitiveParameter] string $secret): string
    {
        return strlen($secret) > 8 ? '…' . substr($secret, -4) : '****';
    }

    /** @param string $context свързва шифротекста с конкретен запис, напр. "provider:openai" */
    public function encrypt(#[\SensitiveParameter] string $plaintext, string $context): string
    {
        $iv = random_bytes(self::IV_LENGTH);
        $tag = '';
        $cipher = openssl_encrypt($plaintext, self::CIPHER, $this->key, OPENSSL_RAW_DATA, $iv, $tag, $context, self::TAG_LENGTH);
        if ($cipher === false) {
            throw new AiException('Неуспешно криптиране.');
        }

        return self::VERSION . ':' . base64_encode($iv . $tag . $cipher);
    }

    public function decrypt(string $payload, string $context): string
    {
        [$version, $encoded] = array_pad(explode(':', $payload, 2), 2, '');
        $raw = base64_decode($encoded, true);

        if ($version !== self::VERSION || $raw === false || strlen($raw) < self::IV_LENGTH + self::TAG_LENGTH) {
            throw new AiException('Невалиден формат на шифрирания ключ.');
        }

        $iv = substr($raw, 0, self::IV_LENGTH);
        $tag = substr($raw, self::IV_LENGTH, self::TAG_LENGTH);
        $plain = openssl_decrypt(substr($raw, self::IV_LENGTH + self::TAG_LENGTH), self::CIPHER, $this->key, OPENSSL_RAW_DATA, $iv, $tag, $context);

        // Едно общо съобщение: грешен ключ, променен шифротекст и чужд контекст не се различават отвън.
        if ($plain === false) {
            throw new AiException('Неуспешно разшифроване на ключа.');
        }

        return $plain;
    }

    public function __debugInfo(): array
    {
        return [];
    }
}
