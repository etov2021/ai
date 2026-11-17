<?php

namespace AiAdmin\Controller\Admin;

use Ai\Exception\AiException;
use Ai\Security\Redactor;
use AiAdmin\Service\BaseController;
use Micro\Form\Form;
use Micro\Http\UploadedFile;

class Knowledge extends BaseController
{
    private const MAX_FILES = 20;

    public function indexAction()
    {
        $query = trim((string) ($this->request->getQueryParams()['q'] ?? ''));

        // Отговорът идва от askAction през сесията (пренасочване след POST) и се показва само веднъж.
        $session = session('AiKnowledge');
        $answer = $session->answer ?? null;
        unset($session->answer);

        return [
            'form' => new Form($this->formPath('knowledge')),
            'rows' => $this->safely(fn () => $this->ai()->documents()->all()),
            'agents' => $this->docsAgents(),
            'answer' => is_array($answer) ? $answer : null,
            'query' => $query,
            'results' => $query === '' ? [] : $this->safely(fn () => $this->ai()->documentRetriever()->retrieve($query, 6)),
            'uploadUrl' => $this->url('knowledge', 'upload'),
            'deleteUrl' => $this->url('knowledge', 'delete'),
            'reindexUrl' => $this->url('knowledge', 'reindex'),
            'embedStatus' => $this->embedStatus(),
            'askUrl' => $this->url('knowledge', 'ask'),
            'searchUrl' => $this->url('knowledge'),
            'homeUrl' => $this->url('index'),
        ];
    }

    /** Задава въпрос на агент, който има инструменти за документи; отговорът се показва на страницата. */
    public function askAction()
    {
        $form = new Form($this->formPath('knowledge'));
        $post = $this->post();
        if (!$this->request->isPost() || !$form->isValid($post)) {
            return $this->redirect($this->url('knowledge'));
        }

        $question = trim((string) ($post['question'] ?? ''));
        $agent = $this->docsAgents()[(string) ($post['agent'] ?? '')] ?? null;

        if ($agent === null) {
            return $this->redirect($this->url('knowledge'), 'Изберете агент с инструменти за документи.', 'danger');
        }
        if ($question === '' || mb_strlen($question) > 2000) {
            return $this->redirect($this->url('knowledge'), 'Въведете въпрос до 2000 знака.', 'danger');
        }

        @set_time_limit(300);

        try {
            $result = $this->ai()->agentService()->run($agent->code, $question, [], null, $this->userId());
            if ($result->content === null || trim($result->content) === '') {
                throw new AiException(sprintf('Агентът не завърши (%s).', $result->status->value));
            }

            session('AiKnowledge')->answer = [
                'agent' => $agent->name,
                'question' => $question,
                'text' => trim($result->content),
                'usage' => sprintf(
                    'Токени: %d вход / %d изход, разход: %.4f EUR, време: %d мс.',
                    $result->usage->inputTokens,
                    $result->usage->outputTokens,
                    $result->cost,
                    $result->durationMs,
                ),
            ];

            return $this->redirect($this->url('knowledge'));
        } catch (AiException $e) {
            return $this->redirect($this->url('knowledge'), Redactor::redact($e->getMessage()), 'danger');
        }
    }

    /** @return array<string, \Ai\Agent\Agent> активни агенти, на които е разрешен поне един инструмент за документи, по код */
    private function docsAgents(): array
    {
        $agents = [];
        foreach ($this->safely(fn () => $this->ai()->agents()->all()) as $agent) {
            if ($agent->runnable && array_intersect($agent->toolCodes, ['docs_search', 'docs_list', 'docs_read']) !== []) {
                $agents[$agent->code] = $agent;
            }
        }

        return $agents;
    }

    public function uploadAction()
    {
        $form = new Form($this->formPath('knowledge'));
        if (!$this->request->isPost() || !$form->isValid($this->post())) {
            return $this->redirect($this->url('knowledge'));
        }

        $files = $this->request->getUploadedFiles()['documents'] ?? [];
        $files = $files instanceof UploadedFile ? [$files] : array_values(array_filter((array) $files, static fn ($f) => $f instanceof UploadedFile));
        $files = array_values(array_filter($files, static fn (UploadedFile $f) => $f->getError() !== UPLOAD_ERR_NO_FILE));

        if ($files === []) {
            return $this->redirect($this->url('knowledge'), 'Изберете поне един файл.', 'danger');
        }
        if (count($files) > self::MAX_FILES) {
            return $this->redirect($this->url('knowledge'), sprintf('Най-много %d файла наведнъж.', self::MAX_FILES), 'danger');
        }

        // Големи PDF и много файлове могат да отнемат повече от времето по подразбиране.
        @set_time_limit(300);

        $added = 0;
        foreach ($files as $file) {
            $name = (string) $file->getClientFilename();

            if ($file->getError() !== UPLOAD_ERR_OK) {
                flash()->setMessage(sprintf('%s: файлът не може да бъде качен (лимит на сървъра или прекъсната връзка).', $name), 'danger');
                continue;
            }

            $tmp = tempnam(sys_get_temp_dir(), 'aikb');
            try {
                $file->moveTo($tmp);
                $this->ai()->documentImporter()->import($tmp, $name, $this->userId());
                $added++;
            } catch (AiException $e) {
                flash()->setMessage(sprintf('%s: %s', $name, Redactor::redact($e->getMessage())), 'danger');
            } finally {
                if (is_file($tmp)) {
                    unlink($tmp);
                }
            }
        }

        // Новите документи се индексират веднага; при грешка търсенето по ключови думи работи и индексът се допълва по-късно.
        if ($added > 0 && ($indexer = $this->ai()->documentIndexer()) !== null) {
            try {
                $result = $indexer->run(500);
                if ($result['error'] !== null) {
                    flash()->setMessage('Индексирането по смисъл не завърши: ' . Redactor::redact($result['error']), 'danger');
                }
            } catch (\Throwable) {
                flash()->setMessage('Индексирането по смисъл не е възможно (изпълнена ли е миграция 013?).', 'danger');
            }
        }

        return $this->redirect($this->url('knowledge'), $added > 0 ? sprintf('Добавени документи: %d.', $added) : null);
    }

    /** @return array{indexed: int, total: int, model: string}|false|null null = търсенето по смисъл не е настроено; false при липсваща таблица */
    private function embedStatus(): array|false|null
    {
        $embedder = $this->ai()->embedder();
        if ($embedder === null) {
            return null;
        }

        try {
            return $this->ai()->embeddings()->status($embedder->model()) + ['model' => $embedder->model()];
        } catch (\Throwable) {
            return false;
        }
    }

    /** Създава вектори за частите, които още нямат; при голям брой се пуска повторно или от cronjobs/embed-documents.php. */
    public function reindexAction()
    {
        $form = new Form($this->formPath('knowledge'));
        if (!$this->request->isPost() || !$form->isValid($this->post())) {
            return $this->redirect($this->url('knowledge'));
        }

        $indexer = $this->ai()->documentIndexer();
        if ($indexer === null) {
            return $this->redirect($this->url('knowledge'), 'Търсенето по смисъл не е настроено (config ai.knowledge.embeddings).', 'danger');
        }

        @set_time_limit(300);

        try {
            $result = $indexer->run(500);
        } catch (\Throwable) {
            return $this->redirect($this->url('knowledge'), 'Индексирането не успя. Изпълнена ли е миграция 013?', 'danger');
        }

        if ($result['error'] !== null) {
            return $this->redirect($this->url('knowledge'), sprintf('Индексирани: %d. Спряно: %s', $result['indexed'], Redactor::redact($result['error'])), 'danger');
        }

        return $this->redirect($this->url('knowledge'), sprintf('Индексирани части: %d, оставащи: %d.', $result['indexed'], $result['remaining']));
    }

    public function deleteAction()
    {
        $form = new Form($this->formPath('knowledge'));
        $post = $this->post();

        if ($this->request->isPost() && $form->isValid($post) && ctype_digit((string) ($post['id'] ?? ''))) {
            try {
                $this->ai()->documents()->delete((int) $post['id']);

                return $this->redirect($this->url('knowledge'), 'Документът е премахнат.');
            } catch (AiException $e) {
                return $this->redirect($this->url('knowledge'), $e->getMessage(), 'danger');
            }
        }

        return $this->redirect($this->url('knowledge'));
    }
}
