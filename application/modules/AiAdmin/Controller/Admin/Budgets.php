<?php

namespace AiAdmin\Controller\Admin;

use Ai\Exception\AiException;
use AiAdmin\Service\BaseController;
use Micro\Form\Form;

class Budgets extends BaseController
{
    public function indexAction()
    {
        $rules = $this->safely(fn () => $this->ai()->budgets()->all());

        $form = new Form($this->formPath('budgets-edit'));
        $form->getElement('scope')->setMultiOptions([
            'global' => 'Глобално (всичко)',
            'provider' => 'Доставчик (код)',
            'agent' => 'Агент (ID)',
            'user' => 'Потребител (ID)',
        ]);
        $form->populate($this->prefill($rules));

        if ($this->request->isPost()) {
            $post = $this->post();

            if ($form->isValid($post)) {
                try {
                    $this->ai()->budgets()->save(
                        (string) $post['scope'],
                        (string) ($post['scopeKey'] ?? ''),
                        self::intOrNull($post['rateLimitPerMinute'] ?? ''),
                        self::decimalOrNull($post['dailyLimit'] ?? ''),
                        self::decimalOrNull($post['monthlyLimit'] ?? ''),
                        (int) $post['warnPercent'],
                        !empty($post['active']),
                    );

                    return $this->redirect($this->url('budgets'), 'Правилото е записано');
                } catch (AiException $e) {
                    flash()->setMessage($e->getMessage(), 'danger');
                }
            }
        }

        return [
            'form' => $form,
            'rows' => array_map(fn (array $r) => [
                'rule' => $r,
                'edit' => $this->url('budgets', 'index', null, ['scope' => $r['scope'], 'key' => $r['scopeKey']]),
            ], $rules),
            'homeUrl' => $this->url('index'),
        ];
    }

    /** @param list<array<string, mixed>> $rules */
    private function prefill(array $rules): array
    {
        $scope = (string) $this->request->getParam('scope', '');
        $key = (string) $this->request->getParam('key', '');

        foreach ($rules as $rule) {
            if ($rule['scope'] === $scope && (string) $rule['scopeKey'] === $key) {
                return [
                    'scope' => $scope, 'scopeKey' => $key, 'rateLimitPerMinute' => $rule['rateLimitPerMinute'],
                    'dailyLimit' => $rule['dailyLimit'], 'monthlyLimit' => $rule['monthlyLimit'],
                    'warnPercent' => $rule['warnPercent'], 'active' => $rule['active'] ? 1 : 0,
                ];
            }
        }

        return ['scope' => 'user', 'scopeKey' => '*', 'warnPercent' => 80, 'active' => 1];
    }
}
