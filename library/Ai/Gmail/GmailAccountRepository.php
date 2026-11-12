<?php

declare(strict_types=1);

namespace Ai\Gmail;

use Ai\Exception\AiException;
use Ai\Security\KeyVault;
use Ai\Storage\DatabaseInterface;
use Ai\Storage\Sql;

/** Свързаните Gmail акаунти; refresh токенът се пази само криптиран. */
final class GmailAccountRepository
{
    private const TABLE = 'AiGmailAccounts';

    public function __construct(private DatabaseInterface $db, private KeyVault $vault)
    {
    }

    /** @return list<array{id: int, email: string, scope: string, created: string}> */
    public function all(): array
    {
        return array_map(
            static fn (array $r): array => ['id' => (int) $r['id'], 'email' => (string) $r['email'], 'scope' => (string) $r['scope'], 'created' => (string) $r['created']],
            $this->db->fetchAll('SELECT `id`, `email`, `scope`, `created` FROM `AiGmailAccounts` ORDER BY `id`'),
        );
    }

    /** Инструментите ползват един акаунт: първият свързан. */
    public function refreshToken(): ?string
    {
        $row = $this->db->fetchRow('SELECT `email`, `refreshTokenEnc` FROM `AiGmailAccounts` ORDER BY `id` LIMIT 1');

        return $row === null ? null : $this->vault->decrypt((string) $row['refreshTokenEnc'], 'gmail:' . $row['email']);
    }

    public function save(string $email, #[\SensitiveParameter] string $refreshToken, string $scope): void
    {
        if ($email === '' || mb_strlen($email) > 255) {
            throw new AiException('Невалиден имейл адрес на акаунта.');
        }

        $now = date('Y-m-d H:i:s');
        $data = ['refreshTokenEnc' => $this->vault->encrypt($refreshToken, 'gmail:' . $email), 'scope' => mb_substr($scope, 0, 255), 'updated' => $now];

        if ($this->db->fetchRow('SELECT `id` FROM `AiGmailAccounts` WHERE `email` = ?', [$email]) === null) {
            $data += ['email' => $email, 'created' => $now];
            $this->db->execute(Sql::insert(self::TABLE, array_keys($data)), array_values($data));
        } else {
            $this->db->execute(Sql::update(self::TABLE, array_keys($data), 'email'), [...array_values($data), $email]);
        }
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM `AiGmailAccounts` WHERE `id` = ?', [$id]);
    }
}
