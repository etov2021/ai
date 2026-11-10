<?php

declare(strict_types=1);

namespace Ai\Limits;

use Ai\Storage\DatabaseInterface;

/** Фиксиран прозорец: лимитът важи за всеки отделен прозорец, така че на границата са възможни до 2x заявки. */
final class RateLimiter
{
    private const CLEANUP_AFTER = 3600;

    public function __construct(private DatabaseInterface $db)
    {
    }

    /** @return ?int null, ако заявката е позволена; иначе секунди до следващия прозорец */
    public function hit(string $bucket, int $limit, int $now, int $windowSeconds = 60): ?int
    {
        $bucket = substr($bucket, 0, 100);
        $start = intdiv($now, $windowSeconds) * $windowSeconds;

        $updated = $this->db->execute('UPDATE `AiRateLimit` SET `hits` = `hits` + 1 WHERE `bucket` = ? AND `windowStart` = ?', [$bucket, $start]);
        if ($updated === 0) {
            try {
                $this->db->execute('INSERT INTO `AiRateLimit` (`bucket`, `windowStart`, `hits`) VALUES (?, ?, 1)', [$bucket, $start]);
                $this->db->execute('DELETE FROM `AiRateLimit` WHERE `windowStart` < ?', [$now - self::CLEANUP_AFTER]);
            } catch (\Throwable) {
                // Паралелна заявка е създала реда първа: увеличаваме нейния брояч.
                $this->db->execute('UPDATE `AiRateLimit` SET `hits` = `hits` + 1 WHERE `bucket` = ? AND `windowStart` = ?', [$bucket, $start]);
            }
        }

        $row = $this->db->fetchRow('SELECT `hits` FROM `AiRateLimit` WHERE `bucket` = ? AND `windowStart` = ?', [$bucket, $start]);

        return (int) ($row['hits'] ?? 0) > $limit ? $start + $windowSeconds - $now : null;
    }
}
