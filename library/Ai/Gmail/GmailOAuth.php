<?php

declare(strict_types=1);

namespace Ai\Gmail;

use Ai\Contract\HttpClientInterface;
use Ai\Exception\AiException;
use Ai\Exception\HttpException;

/** Свързване на акаунт през OAuth2 (authorization code). Иска само право за четене. */
final class GmailOAuth
{
    public const SCOPE = 'https://www.googleapis.com/auth/gmail.readonly';
    public const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    public const PROFILE_URL = 'https://gmail.googleapis.com/gmail/v1/users/me/profile';
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    public function __construct(
        private HttpClientInterface $http,
        private ?string $clientId,
        #[\SensitiveParameter] private ?string $clientSecret,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->clientId !== null && $this->clientId !== '' && $this->clientSecret !== null && $this->clientSecret !== '';
    }

    public function authorizationUrl(string $redirectUri, string $state): string
    {
        $this->assertConfigured();

        // offline + consent: без тях Google не връща refresh токен при повторно свързване.
        return self::AUTH_URL . '?' . http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => self::SCOPE,
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /** @return array{refreshToken: string, email: string} */
    public function connect(string $code, string $redirectUri): array
    {
        $this->assertConfigured();

        $token = $this->json(fn () => $this->http->post(
            self::TOKEN_URL,
            ['Content-Type' => 'application/x-www-form-urlencoded', 'Accept' => 'application/json'],
            http_build_query([
                'code' => $code,
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'redirect_uri' => $redirectUri,
                'grant_type' => 'authorization_code',
            ]),
            15,
        ), 'Google отхвърли кода за достъп. Опитайте да свържете акаунта отново.');

        $access = (string) ($token['access_token'] ?? '');
        $refresh = (string) ($token['refresh_token'] ?? '');
        if ($access === '' || $refresh === '') {
            throw new AiException('Google не върна refresh токен. Премахнете достъпа на приложението от акаунта на Google и свържете отново.');
        }

        $profile = $this->json(fn () => $this->http->get(self::PROFILE_URL, ['Authorization' => 'Bearer ' . $access, 'Accept' => 'application/json'], 15), 'Не може да се прочете профилът на Gmail.');
        $email = (string) ($profile['emailAddress'] ?? '');
        if ($email === '') {
            throw new AiException('Gmail не върна имейл адрес на акаунта.');
        }

        return ['refreshToken' => $refresh, 'email' => $email];
    }

    private function assertConfigured(): void
    {
        if (!$this->isConfigured()) {
            throw new AiException('Липсват GOOGLE_CLIENT_ID и GOOGLE_CLIENT_SECRET в средата.');
        }
    }

    /** @return array<string, mixed> */
    private function json(\Closure $request, string $failure): array
    {
        try {
            $response = $request();
        } catch (HttpException) {
            throw new AiException('Google не отговаря.');
        }

        $data = json_decode($response->body, true);
        if ($response->status !== 200 || !is_array($data)) {
            throw new AiException($failure);
        }

        return $data;
    }
}
