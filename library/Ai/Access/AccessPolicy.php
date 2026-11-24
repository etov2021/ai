<?php

declare(strict_types=1);

namespace Ai\Access;

use Ai\Exception\AiException;
use Ai\Storage\DatabaseInterface;

/**
 * Права по агент и инструмент според ролите на потребителя (алиасите на групите му).
 *
 * Правило "няма редове" = достъпно за всички; иначе само за изброените роли. Контекст без потребител (cron, MCP, работни потоци)
 * не се ограничава тук: той има собствени ограничения (токен, разрешени инструменти). Вложено изпълнение на агент от агент
 * наследява потребителя (виж AgentService), така че ограничен агент не се заобикаля през друг.
 */
final class AccessPolicy
{
    public const AGENT = 'agent';
    public const TOOL = 'tool';

    /** @var array<string, array<string, list<string>>> */
    private array $rules = [];

    /** @var array<int, list<string>> */
    private array $roles = [];

    /**
     * @param \Closure(int): list<string> $rolesOf ролите на потребителя
     * @param list<string> $bypassRoles роли, които не се ограничават (напр. администраторите)
     */
    public function __construct(private DatabaseInterface $db, private \Closure $rolesOf, private array $bypassRoles = [])
    {
    }

    public function canRunAgent(string $agentCode, ?int $userId): bool
    {
        return $this->allowed(self::AGENT, $agentCode, $userId);
    }

    public function canUseTool(string $toolCode, ?int $userId): bool
    {
        return $this->allowed(self::TOOL, $toolCode, $userId);
    }

    /** @return array<string, list<string>> код => роли; кодовете без правила не са в списъка */
    public function allRules(string $type): array
    {
        if (!isset($this->rules[$type])) {
            $rules = [];
            try {
                $rows = $this->db->fetchAll('SELECT `subjectCode`, `role` FROM `AiAccessRules` WHERE `subjectType` = ? ORDER BY `subjectCode`, `role`', [$type]);
            } catch (\Throwable $e) {
                // Липсваща таблица (миграция 018 не е изпълнена) означава "няма правила"; всяка друга грешка не отваря достъпа.
                if (preg_match('/42S02|doesn\'t exist|no such table/i', $e->getMessage()) !== 1) {
                    throw $e;
                }
                $rows = [];
            }
            foreach ($rows as $row) {
                $rules[(string) $row['subjectCode']][] = (string) $row['role'];
            }
            $this->rules[$type] = $rules;
        }

        return $this->rules[$type];
    }

    /**
     * @param list<string> $roles празен списък = достъпно за всички
     * @throws AiException
     */
    public function setRules(string $type, string $code, array $roles): void
    {
        if (!in_array($type, [self::AGENT, self::TOOL], true) || preg_match('/^[a-z][a-z0-9_-]{1,39}$/', $code) !== 1) {
            throw new AiException('Невалиден агент или инструмент.');
        }
        $roles = array_values(array_unique($roles));
        foreach ($roles as $role) {
            if (preg_match('/^[A-Za-z0-9_.-]{1,60}$/', $role) !== 1) {
                throw new AiException('Невалидна роля: ' . mb_substr($role, 0, 30));
            }
        }

        $this->db->execute('DELETE FROM `AiAccessRules` WHERE `subjectType` = ? AND `subjectCode` = ?', [$type, $code]);
        foreach ($roles as $role) {
            $this->db->execute('INSERT INTO `AiAccessRules` (`subjectType`, `subjectCode`, `role`) VALUES (?, ?, ?)', [$type, $code, $role]);
        }
        unset($this->rules[$type]);
    }

    private function allowed(string $type, string $code, ?int $userId): bool
    {
        if ($userId === null) {
            return true;
        }

        $required = $this->allRules($type)[$code] ?? [];
        if ($required === []) {
            return true;
        }

        $roles = $this->roles[$userId] ??= ($this->rolesOf)($userId);

        return array_intersect($this->bypassRoles, $roles) !== [] || array_intersect($required, $roles) !== [];
    }
}
