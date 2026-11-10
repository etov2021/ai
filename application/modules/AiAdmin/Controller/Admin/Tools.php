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
                $parameters = json_decode((string) $post['parameters'], true);
                $configText = (string) ($post['config'] ?? '');
                $config = $configText === '' ? [] : json_decode($configText, true);

                if (!is_array($parameters)) {
                    $form->getElement('parameters')->addError('Невалиден JSON');
                } elseif (!is_array($config)) {
                    $form->getElement('config')->addError('Невалиден JSON');
                } else {
                    try {
                        $saved = $this->ai()->tools()->save(
                            $existing ? $existing->code : (string) $post['code'],
                            (string) $post['name'],
                            (string) $post['description'],
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

        return ['form' => $form, 'item' => $existing];
    }
}
