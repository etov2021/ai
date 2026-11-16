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

        return [
            'form' => new Form($this->formPath('knowledge')),
            'rows' => $this->safely(fn () => $this->ai()->documents()->all()),
            'query' => $query,
            'results' => $query === '' ? [] : $this->safely(fn () => $this->ai()->documentRetriever()->retrieve($query, 6)),
            'uploadUrl' => $this->url('knowledge', 'upload'),
            'deleteUrl' => $this->url('knowledge', 'delete'),
            'searchUrl' => $this->url('knowledge'),
            'homeUrl' => $this->url('index'),
        ];
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

        return $this->redirect($this->url('knowledge'), $added > 0 ? sprintf('Добавени документи: %d.', $added) : null);
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
