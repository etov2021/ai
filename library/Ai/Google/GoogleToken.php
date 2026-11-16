<?php

declare(strict_types=1);

namespace Ai\Google;

use Ai\Contract\HttpClientInterface;
use Ai\Exception\AiException;
use Ai\Exception\HttpException;
use Ai\Exception\ToolException;

/** Краткотраен токен за достъп до Google API по записания refresh токен. Съобщенията са безопасни за модела. */
final class GoogleToken
{
    public const URL = 'https://oauth2.googleapis.com/token';

    private ?string $accessToken = null;
    private int $expiresAt = 0;

    /** @param \Closure(): ?string $refreshToken чете се лениво, така че липсващ AI_MASTER_KEY да не чупи регистрацията */
    public function __construct(
        private HttpClientInterface $http,
        private ?string $clientId,
        #[\SensitiveParameter] private ?string $clientSecret,
        private \Closure $refreshToken,
    ) {
    }

    public function get(): string
    {
        if ($this->accessToken !== null && time() < $this->expiresAt - 30) {
            return $this->accessToken;
        }
        if ($this->clientId === null || $this->clientId === '' || $this->clientSecret === null || $this->clientSecret === '') {
            throw new ToolException('Google акаунтът не е настроен от администратора.');
        }

        try {
            $refresh = ($this->refreshToken)();
        } catch (AiException) {
            throw new ToolException('Google акаунтът не е свързан.');
        }
        if ($refresh === null) {
            throw new ToolException('Google акаунтът не е свързан.');
        }

        try {
            $response = $this->http->post(
                self::URL,
                ['Content-Type' => 'application/x-www-form-urlencoded', 'Accept' => 'application/json'],
                http_build_query(['client_id' => $this->clientId, 'client_secret' => $this->clientSecret, 'refresh_token' => $refresh, 'grant_type' => 'refresh_token']),
                15,
            );
        } catch (HttpException) {
            throw new ToolException('Google не отговаря.');
        }

        $data = json_decode($response->body, true);
        if ($response->status !== 200 || !is_array($data) || !is_string($data['access_token'] ?? null)) {
            throw new ToolException('Няма достъп до Google. Акаунтът трябва да се свърже наново от администратора.');
        }

        $this->expiresAt = time() + (int) ($data['expires_in'] ?? 300);

        return $this->accessToken = $data['access_token'];
    }
}
