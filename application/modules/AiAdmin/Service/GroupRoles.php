<?php

namespace AiAdmin\Service;

use Ai\Storage\DatabaseInterface;

/**
 * Роли на потребител = алиасите на активните му групи и на техните родителски групи (както наследяват правата в UserManagement).
 * Група без алиас (или с непозволени знаци) се представя като "group-<id>".
 */
final class GroupRoles
{
    private const MAX_DEPTH = 6;

    public function __construct(private DatabaseInterface $db)
    {
    }

    /** @return list<string> */
    public function rolesOf(int $userId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT g.`id`, g.`alias`, g.`parentId` FROM `UsersGroupsRel` r INNER JOIN `UsersGroups` g ON g.`id` = r.`groupId` WHERE r.`userId` = ? AND g.`active` = 1',
            [$userId],
        );

        $roles = [];
        $seen = [];
        for ($depth = 0; $rows !== [] && $depth < self::MAX_DEPTH; $depth++) {
            $next = [];
            foreach ($rows as $row) {
                $id = (int) $row['id'];
                if (isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
                $roles[] = self::role($row);

                $parentId = (int) ($row['parentId'] ?? 0);
                if ($parentId > 0 && !isset($seen[$parentId])) {
                    $parent = $this->db->fetchRow('SELECT `id`, `alias`, `parentId` FROM `UsersGroups` WHERE `id` = ? AND `active` = 1', [$parentId]);
                    if ($parent !== null) {
                        $next[] = $parent;
                    }
                }
            }
            $rows = $next;
        }

        return array_values(array_unique($roles));
    }

    /** @return array<string, string> роля => име на групата, за таблицата с права */
    public function options(): array
    {
        $options = [];
        foreach ($this->db->fetchAll('SELECT `id`, `name`, `alias` FROM `UsersGroups` WHERE `active` = 1 ORDER BY `name`') as $row) {
            $options[self::role($row)] = (string) $row['name'];
        }

        return $options;
    }

    /** @param array<string, mixed> $group */
    private static function role(array $group): string
    {
        $alias = trim((string) ($group['alias'] ?? ''));

        return preg_match('/^[A-Za-z0-9_.-]{1,60}$/', $alias) === 1 ? $alias : 'group-' . (int) $group['id'];
    }
}
