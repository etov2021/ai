<?php

namespace AiAdmin\Controller\Admin;

use Ai\Exception\AiException;
use AiAdmin\Service\BaseController;
use Micro\Exception\Exception as CoreException;
use Micro\Form\Form;

class Tools extends BaseController
{
    private const EMPTY_SCHEMA = '{"type": "object", "properties": {}}';

    public function indexAction()
    {
        $tools = $this->safely(fn () => $this->ai()->tools()->all());

        return [
            'rows' => array_map(fn ($t) => ['item' => $t, 'edit' => $this->url('tools', 'edit', $t->code)], $tools),
            'addUrl' => $this->url('tools', 'edit'),
            'homeUrl' => $this->url('index'),
        ];
    }

    public function editAction()
    {
        $code = (string) $this->request->getParam('id', '');
        $existing = $code !== '' ? $this->ai()->tools()->findByCode($code) : null;
        if ($code !== '' && $existing === null) {
            throw new CoreException('Записът не е намерен', 404);
        }

        $form = new Form($this->formPath('tools-edit'));
        $form->getElement('type')->setMultiOptions(['builtin' => 'Вграден (PHP клас, регистриран в кода)', 'http' => 'HTTP (външно API по шаблон)']);

        if ($existing) {
            $form->removeElement('code');
            $form->populate([
                'name' => $existing->name, 'description' => $existing->description, 'type' => $existing->type,
                'parameters' => json_encode($existing->parameters, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'config' => $existing->config === [] ? '' : json_encode($existing->config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'active' => $existing->active ? 1 : 0,
            ]);
        } else {
            $form->populate([
                'type' => 'http', 'parameters' => self::EMPTY_SCHEMA, 'active' => 1,
                'config' => json_encode(['method' => 'GET', 'url' => 'https://api.example.com/items/{id}', 'allowedHosts' => ['api.example.com']], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            ]);
        }

        if ($this->request->isPost()) {
            $post = $this->post();

            if (isset($post['btnBack'])) {
                return $this->redirect($this->url('tools'));
            }

            if ($form->isValid($post)) {
                // Вградените инструменти взимат параметрите от кода, затова празното поле е в ред.
                $parameters = json_decode(trim((string) ($post['parameters'] ?? '')) === '' ? self::EMPTY_SCHEMA : (string) $post['parameters'], true);
                $configText = (string) ($post['config'] ?? '');
                $config = $configText === '' ? [] : json_decode($configText, true);
                // Скритият в изгледа пример за HTTP не се записва към вграден инструмент.
                if ((string) ($post['type'] ?? '') === 'builtin') {
                    $config = [];
                }

                if (!is_array($parameters)) {
                    $form->getElement('parameters')->addError('Невалиден JSON');
                } elseif (!is_array($config)) {
                    $form->getElement('config')->addError('Невалиден JSON');
                } elseif (trim((string) ($post['description'] ?? '')) === '' && (string) $post['type'] !== 'builtin') {
                    $form->getElement('description')->addError('Описанието е задължително за HTTP инструмент: то се подава на модела.');
                } else {
                    try {
                        $saved = $this->ai()->tools()->save(
                            $existing ? $existing->code : (string) $post['code'],
                            (string) $post['name'],
                            // Вграденият инструмент подава на модела описанието от кода; празното поле се попълва с името, за да не е празно в базата.
                            trim((string) ($post['description'] ?? '')) !== '' ? (string) $post['description'] : (string) $post['name'],
                            (string) $post['type'],
                            $parameters,
                            $config,
                            !empty($post['active']),
                        );

                        return $this->redirect(
                            isset($post['btnApply']) ? $this->url('tools', 'edit', $saved->code) : $this->url('tools'),
                            'Информацията е записана',
                        );
                    } catch (AiException $e) {
                        flash()->setMessage($e->getMessage(), 'danger');
                    }
                }
            }
        }

        return ['form' => $form, 'item' => $existing, 'builtins' => $existing ? [] : $this->builtinChoices()];
    }

    /**
     * Регистрираните вградени инструменти за избор при нов запис; вече добавените са отбелязани.
     *
     * @return list<array{code: string, description: string, added: bool}>
     */
    private function builtinChoices(): array
    {
        try {
            $added = array_map(static fn ($t) => $t->code, $this->ai()->tools()->all());
            $choices = [];
            foreach ($this->ai()->toolRegistry()->builtinDefinitions() as $code => $definition) {
                $choices[] = ['code' => (string) $code, 'description' => $definition->description, 'added' => in_array($code, $added, true)];
            }

            return $choices;
        } catch (\Throwable) {
            return [];
        }
    }
}
