<?php

namespace AiAdmin\Controller\Admin;

use Ai\Exception\AiException;
use Ai\Security\Redactor;
use AiAdmin\Service\BaseController;
use Micro\Exception\Exception as CoreException;
use Micro\Form\Form;

class Workflows extends BaseController
{
    private const FLAGS = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    private const SAMPLE = [
        'steps' => [
            ['id' => 'draft', 'type' => 'agent', 'agent' => 'helper', 'input' => '{{input}}'],
            ['id' => 'check', 'type' => 'agent', 'agent' => 'helper', 'input' => "Провери и подобри текста:\n{{draft}}"],
        ],
    ];

    public function indexAction()
    {
        $workflows = $this->safely(fn () => $this->ai()->workflows()->all());

        return [
            'rows' => array_map(fn ($w) => [
                'item' => $w,
                'edit' => $this->url('workflows', 'edit', $w->code),
                'run' => $this->url('workflows', 'run', $w->code),
            ], $workflows),
            'addUrl' => $this->url('workflows', 'edit'),
            'homeUrl' => $this->url('index'),
        ];
    }

    public function editAction()
    {
        $code = (string) $this->request->getParam('id', '');
        $existing = $code !== '' ? $this->ai()->workflows()->findByCode($code) : null;
        if ($code !== '' && $existing === null) {
            throw new CoreException('Записът не е намерен', 404);
        }

        $form = new Form($this->formPath('workflows-edit'));

        if ($existing) {
            $form->removeElement('code');
            $form->populate([
                'name' => $existing->name,
                'description' => $existing->description ?? '',
                'definition' => json_encode($existing->definition->toArray(), self::FLAGS),
                'active' => $existing->active ? 1 : 0,
            ]);
        } else {
            $form->populate(['definition' => json_encode(self::SAMPLE, self::FLAGS), 'active' => 1]);
        }

        if ($this->request->isPost()) {
            $post = $this->post();

            if (isset($post['btnBack'])) {
                return $this->redirect($this->url('workflows'));
            }

            if ($form->isValid($post)) {
                $definition = json_decode((string) $post['definition'], true);

                if (!is_array($definition)) {
                    $form->getElement('definition')->addError('Невалиден JSON');
                } else {
                    try {
                        $saved = $this->ai()->workflows()->save(
                            $existing ? $existing->code : (string) $post['code'],
                            (string) $post['name'],
                            (string) ($post['description'] ?? ''),
                            $definition,
                            !empty($post['active']),
                        );

                        return $this->redirect(
                            isset($post['btnApply']) ? $this->url('workflows', 'edit', $saved->code) : $this->url('workflows'),
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

    public function runAction()
    {
        $code = (string) $this->request->getParam('id', '');
        $workflow = $this->ai()->workflows()->findByCode($code);
        if ($workflow === null) {
            throw new CoreException('Записът не е намерен', 404);
        }

        $form = new Form($this->formPath('workflows-run'));
        $result = null;

        if ($this->request->isPost()) {
            $post = $this->post();

            if ($form->isValid($post)) {
                try {
                    $result = $this->ai()->workflowService()->run($workflow->code, (string) $post['input'], $this->userId());
                } catch (AiException $e) {
                    flash()->setMessage(Redactor::redact($e->getMessage()), 'danger');
                }
            }
        }

        return ['form' => $form, 'item' => $workflow, 'result' => $result, 'backUrl' => $this->url('workflows')];
    }
}
