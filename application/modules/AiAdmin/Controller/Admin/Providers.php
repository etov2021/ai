<?php

namespace AiAdmin\Controller\Admin;

use Ai\Exception\AiException;
use AiAdmin\Service\BaseController;
use Micro\Exception\Exception as CoreException;
use Micro\Form\Form;

class Providers extends BaseController
{
    private const TYPE_LABELS = [
        'openai' => 'OpenAI',
        'anthropic' => 'Anthropic (Claude)',
        'gemini' => 'Google Gemini',
        'openai_compatible' => 'OpenAI-съвместим (Mistral, Groq, LM Studio и др.)',
        'ollama' => 'Ollama (локален, без ключ)',
    ];

    public function indexAction()
    {
        $items = $this->safely(fn () => $this->ai()->providers()->all());

        return [
            'rows' => array_map(fn ($p) => [
                'item' => $p,
                'edit' => $this->url('providers', 'edit', $p->code),
                'models' => $this->url('models', 'index', $p->code),
            ], $items),
            'addUrl' => $this->url('providers', 'edit'),
            'homeUrl' => $this->url('index'),
        ];
    }

    public function editAction()
    {
        $code = (string) $this->request->getParam('id', '');
        $existing = null;

        if ($code !== '') {
            $existing = $this->safely(fn () => $this->ai()->providers()->findByCode($code), false);
            if (!$existing) {
                throw new CoreException('Записът не е намерен', 404);
            }
        }

        $form = new Form($this->formPath('providers-edit'));
        $types = $this->ai()->registry()->types();
        $form->getElement('type')->setMultiOptions(array_combine($types, array_map(fn ($t) => self::TYPE_LABELS[$t] ?? $t, $types)));

        if ($existing) {
            $form->removeElement('code');
            $form->populate([
                'name' => $existing->name, 'type' => $existing->type, 'baseUrl' => $existing->baseUrl,
                'timeoutSeconds' => $existing->timeoutSeconds, 'active' => $existing->active ? 1 : 0,
            ]);
        } else {
            $form->populate(['timeoutSeconds' => 60, 'active' => 1]);
        }

        if ($this->request->isPost()) {
            $post = $this->post();
            
            if (isset($post['btnBack'])) {
                return $this->redirect($this->url('providers'));
            }

            if ($form->isValid($post)) {
                try {
                    $providers = $this->ai()->providers();

                    if (isset($post['btnClearKey']) && $existing) {
                        $providers->clearApiKey($existing->code);

                        return $this->redirect($this->url('providers', 'edit', $existing->code), 'API ключът е изтрит');
                    }

                    $saved = $providers->save(
                        $existing ? $existing->code : (string) $post['code'],
                        (string) $post['name'],
                        (string) $post['type'],
                        (string) ($post['baseUrl'] ?? ''),
                        (int) $post['timeoutSeconds'],
                        !empty($post['active']),
                        (string) ($post['apiKey'] ?? ''),
                    );

                    return $this->redirect(
                        isset($post['btnApply']) ? $this->url('providers', 'edit', $saved->code) : $this->url('providers'),
                        'Информацията е записана',
                    );
                } catch (AiException $e) {
                    flash()->setMessage($e->getMessage(), 'danger');
                }
            }
        }

        return ['form' => $form, 'item' => $existing];
    }
}
