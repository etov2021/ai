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

    /** Добавя моделите на Ollama (изтеглените) и Gemini (от ListModels за ключа) с цена 0; съществуващите не се променят. */
    public function importAction()
    {
        $importable = array_values(array_filter(
            $this->safely(fn () => $this->ai()->providers()->all()),
            static fn ($p) => in_array($p->type, ['ollama', 'gemini'], true) && $p->active,
        ));

        $form = new Form($this->formPath('models-import'));
        $form->getElement('provider')->setMultiOptions(array_column(array_map(static fn ($p) => [$p->code, $p->name . ' (' . $p->type . ')'], $importable), 1, 0));

        if ($this->request->isPost()) {
            $post = $this->post();

            if ($form->isValid($post)) {
                try {
                    $provider = $this->ai()->providers()->findByCode((string) $post['provider']);
                    if ($provider === null || !in_array($provider->type, ['ollama', 'gemini'], true)) {
                        throw new AiException('Импорт се поддържа само за доставчици от тип Ollama и Gemini.');
                    }

                    if ($provider->type === 'gemini') {
                        $key = $this->ai()->providers()->apiKey($provider->code) ?? throw new AiException('За доставчика няма записан API ключ.');
                        $found = $this->ai()->geminiCatalog()->models($key, $provider->baseUrl, min(30, $provider->timeoutSeconds));
                    } else {
                        $names = $this->ai()->ollama()->models($provider->baseUrl ?? ProviderRegistry::OLLAMA_DEFAULT_URL, min(30, $provider->timeoutSeconds));
                        $found = array_map(static fn (string $n): array => ['model' => $n, 'label' => $n, 'contextWindow' => null, 'maxOutputTokens' => null], $names);
                    }

                    $added = $skipped = 0;
                    foreach ($found as $item) {
                        if ($this->ai()->models()->find($provider->id, $item['model']) !== null) {
                            $skipped++;
                            continue;
                        }
                        try {
                            $this->ai()->models()->save($provider->id, $item['model'], $item['label'], 0.0, 0.0, $item['contextWindow'], $item['maxOutputTokens']);
                            $added++;
                        } catch (AiException) {
                            // Модел с невалидно име се пропуска, без да спира целия импорт.
                            $skipped++;
                        }
                    }

                    return $this->redirect($this->url('models', 'index', $provider->code), sprintf('Добавени модели: %d, пропуснати (вече съществуват): %d', $added, $skipped));
                } catch (AiException $e) {
                    flash()->setMessage($e->getMessage(), 'danger');
                }
            }
        }

        return ['form' => $form, 'hasProviders' => $importable !== [], 'backUrl' => $this->url('models')];
    }

    public function editAction()
    {
        $providerCode = (string) $this->request->getParam('id', '');
        // Само от адреса: getParam() чете и тялото на заявката, където полето "model" на формата би се взело за редактиране на съществуващ модел.
        $modelId = (string) ($this->request->getQueryParams()['model'] ?? '');

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
