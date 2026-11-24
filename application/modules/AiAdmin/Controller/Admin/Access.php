<?php

namespace AiAdmin\Controller\Admin;

use Ai\Access\AccessPolicy;
use Ai\Exception\AiException;
use AiAdmin\Service\BaseController;
use Micro\Form\Form;

/**
 * Кои групи потребители могат да изпълняват всеки агент и да ползват всеки инструмент.
 * Без отметка за ред = достъпно за всички. Записва се в одита с точните роли.
 */
class Access extends BaseController
{
    protected bool $autoAudit = false;

    public function indexAction()
    {
        $access = $this->ai()->access();
        $agents = $this->safely(fn () => $this->ai()->agents()->all());
        $tools = $this->safely(fn () => $this->ai()->tools()->all());
        $agentRules = $this->safely(fn () => $access->allRules(AccessPolicy::AGENT));
        $toolRules = $this->safely(fn () => $access->allRules(AccessPolicy::TOOL));
        $roles = $this->roles($agentRules, $toolRules);

        if ($this->request->isPost()) {
            $post = $this->post();
            if ((new Form($this->formPath('knowledge')))->isValid($post)) {
                $subjects = [
                    AccessPolicy::AGENT => array_map(static fn ($a): string => $a->code, $agents),
                    AccessPolicy::TOOL => array_map(static fn ($t): string => $t->code, $tools),
                ];
                try {
                    $changed = $this->save($post, $subjects, ['agent' => $agentRules, 'tool' => $toolRules], array_keys($roles));
                } catch (AiException $e) {
                    return $this->redirect($this->url('access'), $e->getMessage(), 'danger');
                }

                return $this->redirect($this->url('access'), $changed === 0 ? 'Няма промени.' : 'Правата са записани (' . $changed . ').');
            }
        }

        return [
            'form' => new Form($this->formPath('knowledge')),
            'roles' => $roles,
            'bypass' => array_values(array_filter((array) config('ai.access.bypass_groups', []), 'is_string')),
            'sections' => [
                AccessPolicy::AGENT => ['title' => 'Агенти', 'rows' => array_map(static fn ($a): array => ['code' => $a->code, 'name' => $a->name, 'roles' => $agentRules[$a->code] ?? []], $agents)],
                AccessPolicy::TOOL => ['title' => 'Инструменти', 'rows' => array_map(static fn ($t): array => ['code' => $t->code, 'name' => $t->name, 'roles' => $toolRules[$t->code] ?? []], $tools)],
            ],
            'homeUrl' => $this->url('index'),
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @param array<string, list<string>> $subjects
     * @param array<string, array<string, list<string>>> $current
     * @param list<string> $knownRoles
     */
    private function save(array $post, array $subjects, array $current, array $knownRoles): int
    {
        $submitted = is_array($post['rules'] ?? null) ? $post['rules'] : [];
        $changed = 0;

        foreach ($subjects as $type => $codes) {
            foreach ($codes as $code) {
                $roles = $submitted[$type][$code] ?? [];
                // Само познати роли: подмяна на формата не може да въведе произволна.
                $roles = array_values(array_intersect($knownRoles, is_array($roles) ? array_filter($roles, 'is_string') : []));
                $before = $current[$type][$code] ?? [];
                if (array_diff($roles, $before) === [] && array_diff($before, $roles) === []) {
                    continue;
                }

                $this->ai()->access()->setRules($type, $code, $roles);
                $this->audit('access.rules_changed', $type, $code, ['before' => implode(',', $before), 'after' => implode(',', $roles)]);
                $changed++;
            }
        }

        return $changed;
    }

    /**
     * @param array<string, list<string>> $agentRules
     * @param array<string, list<string>> $toolRules
     * @return array<string, string> роля => име за показване (групите от UserManagement, плюс роли от правила без такава група)
     */
    private function roles(array $agentRules, array $toolRules): array
    {
        $roles = $this->safely(fn () => $this->ai()->groupRoles()->options());
        foreach ([$agentRules, $toolRules] as $rules) {
            foreach ($rules as $list) {
                foreach ($list as $role) {
                    $roles[$role] ??= $role . ' (няма такава група)';
                }
            }
        }

        return $roles;
    }
}
