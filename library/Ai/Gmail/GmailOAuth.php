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
    public const CALENDAR_SCOPE = 'https://www.googleapis.com/auth/calendar.events';
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
            'scope' => self::SCOPE . ' ' . self::CALENDAR_SCOPE . ' openid email',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /** @return array{refreshToken: string, email: string, scope: string} */
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

        // Имейлът идва от входа (openid email), за да работи свързването и без разрешена поща (само календар).
        $email = self::emailFromIdToken((string) ($token['id_token'] ?? ''));
        if ($email === '') {
            $email = $this->profileEmail($access);
        }

        return ['refreshToken' => $refresh, 'email' => $email, 'scope' => (string) ($token['scope'] ?? '')];
    }

    private function profileEmail(string $access): string
    {
        try {
            $response = $this->http->get(self::PROFILE_URL, ['Authorization' => 'Bearer ' . $access, 'Accept' => 'application/json'], 15);
        } catch (HttpException) {
            throw new AiException('Google не отговаря.');
        }

        $profile = json_decode($response->body, true);
        $email = is_array($profile) ? (string) ($profile['emailAddress'] ?? '') : '';
        if ($response->status !== 200 || $email === '') {
            throw new AiException(sprintf('Не може да се прочете профилът на Gmail (HTTP %d). Включете Gmail API в Google Cloud и разрешете достъпа до пощата на екрана на Google.', $response->status));
        }

        return $email;
    }

    /** Токенът идва директно от Google по защитена връзка, затова подписът не се проверява: чете се само имейлът за показване и ключ на записа. */
    private static function emailFromIdToken(string $idToken): string
    {
        $parts = explode('.', $idToken);
        if (count($parts) !== 3) {
            return '';
        }
        $payload = json_decode((string) base64_decode(strtr($parts[1], '-_', '+/'), false), true);
        $email = is_array($payload) ? (string) ($payload['email'] ?? '') : '';

        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : '';
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
