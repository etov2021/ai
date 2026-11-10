<?php

namespace AiAdmin\Controller\Admin;

use Ai\Exception\AiException;
use Ai\Registry\ProviderRegistry;
use AiAdmin\Service\BaseController;
use Micro\Exception\Exception as CoreException;
use Micro\Form\Form;

class Models extends BaseController
{
    public function indexAction()
    {
        $only = (string) $this->request->getParam('id', '');
        $groups = [];

        foreach ($this->safely(fn () => $this->ai()->providers()->all()) as $provider) {
            if ($only !== '' && $provider->code !== $only) {
                continue;
            }
            $groups[] = [
                'provider' => $provider,
                'addUrl' => $this->url('models', 'edit', $provider->code),
                'rows' => array_map(fn ($m) => [
                    'item' => $m,
                    'edit' => $this->url('models', 'edit', $provider->code, ['model' => $m->model]),
                ], $this->ai()->models()->forProvider($provider->id)),
            ];
        }

        return ['groups' => $groups, 'homeUrl' => $this->url('index'), 'importUrl' => $this->url('models', 'import')];
    }

    /** Добавя изтеглените в Ollama модели с цена 0; съществуващите не се променят. */
    public function importAction()
    {
        $ollama = array_values(array_filter(
            $this->safely(fn () => $this->ai()->providers()->all()),
            static fn ($p) => $p->type === 'ollama' && $p->active,
        ));

        $form = new Form($this->formPath('models-import'));
        $form->getElement('provider')->setMultiOptions(array_column(array_map(static fn ($p) => [$p->code, $p->name], $ollama), 1, 0));

        if ($this->request->isPost()) {
            $post = $this->post();

            if ($form->isValid($post)) {
                try {
                    $provider = $this->ai()->providers()->findByCode((string) $post['provider']);
                    if ($provider === null || $provider->type !== 'ollama') {
                        throw new AiException('Доставчикът не е от тип Ollama.');
                    }

                    $added = $skipped = 0;
                    $names = $this->ai()->ollama()->models($provider->baseUrl ?? ProviderRegistry::OLLAMA_DEFAULT_URL, min(30, $provider->timeoutSeconds));
                    foreach ($names as $name) {
                        if ($this->ai()->models()->find($provider->id, $name) !== null) {
                            $skipped++;
                            continue;
                        }
                        $this->ai()->models()->save($provider->id, $name, $name, 0.0, 0.0);
                        $added++;
                    }

                    return $this->redirect($this->url('models', 'index', $provider->code), sprintf('Добавени модели: %d, пропуснати (вече съществуват): %d', $added, $skipped));
                } catch (AiException $e) {
                    flash()->setMessage($e->getMessage(), 'danger');
                }
            }
        }

        return ['form' => $form, 'hasOllama' => $ollama !== [], 'backUrl' => $this->url('models')];
    }

    public function editAction()
    {
        $providerCode = (string) $this->request->getParam('id', '');
        $modelId = (string) $this->request->getParam('model', '');

        $provider = $this->safely(fn () => $this->ai()->providers()->findByCode($providerCode), false);
        if (!$provider) {
            throw new CoreException('Доставчикът не е намерен', 404);
        }

        $existing = $modelId !== '' ? $this->ai()->models()->find($provider->id, $modelId) : null;
        if ($modelId !== '' && $existing === null) {
            throw new CoreException('Записът не е намерен', 404);
        }

        $form = new Form($this->formPath('models-edit'));
        if ($existing) {
            $form->removeElement('model');
            $form->populate([
                'label' => $existing->label, 'inputPricePerMillion' => $existing->inputPricePerMillion,
                'outputPricePerMillion' => $existing->outputPricePerMillion, 'contextWindow' => $existing->contextWindow,
                'maxOutputTokens' => $existing->maxOutputTokens, 'supportsTools' => $existing->supportsTools ? 1 : 0,
                'active' => $existing->active ? 1 : 0,
            ]);
        } else {
            $form->populate(['inputPricePerMillion' => 0, 'outputPricePerMillion' => 0, 'supportsTools' => 1, 'active' => 1]);
        }

        if ($this->request->isPost()) {
            $post = $this->post();

            if (isset($post['btnBack'])) {
                return $this->redirect($this->url('models', 'index', $provider->code));
            }

            if ($form->isValid($post)) {
                try {
                    $saved = $this->ai()->models()->save(
                        $provider->id,
                        $existing ? $existing->model : (string) $post['model'],
                        (string) $post['label'],
                        (float) self::decimalOrNull($post['inputPricePerMillion']),
                        (float) self::decimalOrNull($post['outputPricePerMillion']),
                        self::intOrNull($post['contextWindow'] ?? ''),
                        self::intOrNull($post['maxOutputTokens'] ?? ''),
                        !empty($post['supportsTools']),
                        !empty($post['active']),
                    );

                    return $this->redirect(
                        isset($post['btnApply']) ? $this->url('models', 'edit', $provider->code, ['model' => $saved->model]) : $this->url('models', 'index', $provider->code),
                        'Информацията е записана',
                    );
                } catch (AiException $e) {
                    flash()->setMessage($e->getMessage(), 'danger');
                }
            }
        }

        return ['form' => $form, 'item' => $existing, 'provider' => $provider];
    }
}
