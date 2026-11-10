<?php

namespace AiAdmin\Controller\Admin;

use Ai\Exception\AiException;
use AiAdmin\Service\BaseController;
use Micro\Exception\Exception as CoreException;
use Micro\Form\Form;

class Agents extends BaseController
{
    public function indexAction()
    {
        $agents = $this->safely(fn () => $this->ai()->agents()->all());

        return [
            'rows' => array_map(fn ($a) => ['item' => $a, 'edit' => $this->url('agents', 'edit', $a->code)], $agents),
            'addUrl' => $this->url('agents', 'edit'),
            'homeUrl' => $this->url('index'),
        ];
    }

    public function editAction()
    {
        $code = (string) $this->request->getParam('id', '');
        $existing = $code !== '' ? $this->ai()->agents()->findByCode($code) : null;
        if ($code !== '' && $existing === null) {
            throw new CoreException('Записът не е намерен', 404);
        }

        $modelOptions = $this->safely(fn () => $this->ai()->models()->options());
        $tools = $this->safely(fn () => $this->ai()->tools()->all());

        $form = new Form($this->formPath('agents-edit'));
        $form->getElement('modelId')->setMultiOptions($modelOptions);
        $form->getElement('escalationModelId')->setMultiOptions($modelOptions);

        if ($existing) {
            $form->removeElement('code');
            $form->populate([
                'name' => $existing->name, 'systemPrompt' => $existing->systemPrompt, 'modelId' => $existing->modelId,
                'escalationModelId' => $existing->escalation?->modelId,
                'temperature' => $existing->temperature, 'maxTokens' => $existing->maxTokens,
                'maxSteps' => $existing->maxSteps, 'timeoutSeconds' => $existing->timeoutSeconds,
                'active' => $existing->active ? 1 : 0,
            ]);
        } else {
            $form->populate(['maxSteps' => 5, 'timeoutSeconds' => 120, 'active' => 1]);
        }

        $selectedTools = $existing ? $existing->toolCodes : [];

        if ($this->request->isPost()) {
            $post = $this->post();
            $selectedTools = array_values(array_filter((array) ($post['tools'] ?? []), 'is_string'));

            if (isset($post['btnBack'])) {
                return $this->redirect($this->url('agents'));
            }

            if ($form->isValid($post)) {
                try {
                    $saved = $this->ai()->agents()->save(
                        $existing ? $existing->code : (string) $post['code'],
                        (string) $post['name'],
                        (string) $post['systemPrompt'],
                        (int) $post['modelId'],
                        $selectedTools,
                        self::decimalOrNull($post['temperature'] ?? ''),
                        self::intOrNull($post['maxTokens'] ?? ''),
                        (int) $post['maxSteps'],
                        (int) $post['timeoutSeconds'],
                        !empty($post['active']),
                        null,
                        self::intOrNull($post['escalationModelId'] ?? ''),
                    );

                    return $this->redirect(
                        isset($post['btnApply']) ? $this->url('agents', 'edit', $saved->code) : $this->url('agents'),
                        'Информацията е записана',
                    );
                } catch (AiException $e) {
                    flash()->setMessage($e->getMessage(), 'danger');
                }
            }
        }

        return ['form' => $form, 'item' => $existing, 'tools' => $tools, 'selectedTools' => $selectedTools];
    }
}
