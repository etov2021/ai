<?php

namespace AiAdmin\Controller\Admin;

use Ai\Design\HtmlSanitizer;
use Ai\Design\PreviewDocument;
use Ai\Design\TemplatePreview;
use Ai\Exception\AiException;
use Ai\Security\Redactor;
use AiAdmin\Service\BaseController;
use Micro\Exception\Exception as CoreException;
use Micro\Form\Form;
use Micro\Http\UploadedFile;

/** Библиотека с шаблони за сайта (frontend): импорт от поставен HTML, файл (.html или .zip пакет) или адрес на страница. */
class DesignTemplates extends BaseController
{
    public function indexAction()
    {
        $rows = $this->tableRead(fn () => $this->ai()->templates()->all());

        return [
            'form' => new Form($this->formPath('knowledge')),
            'rows' => array_map(fn (array $r) => ['item' => $r, 'url' => $this->url('design-templates', 'view', (string) $r['id'])], $rows ?: []),
            'missingTable' => $rows === false,
            'importUrl' => $this->url('design-templates', 'import'),
            'uploadLimit' => ini_get('upload_max_filesize'),
            'designUrl' => $this->url('design'),
            'homeUrl' => $this->url('index'),
        ];
    }

    public function importAction()
    {
        $post = $this->post();
        if (!$this->request->isPost() || !(new Form($this->formPath('knowledge')))->isValid($post)) {
            return $this->redirect($this->url('design-templates'));
        }

        $name = (string) ($post['name'] ?? '');
        $tags = (string) ($post['tags'] ?? '');
        $source = (string) ($post['source'] ?? '');

        @set_time_limit(300);

        $tmp = null;
        try {
            $importer = $this->ai()->templateImporter();

            if ($source === 'paste') {
                $id = $importer->fromHtml($name, $tags, (string) ($post['html'] ?? ''), $this->userId());
            } elseif ($source === 'url') {
                $id = $importer->fromUrl($name, $tags, (string) ($post['url'] ?? ''), $this->userId());
            } elseif ($source === 'file') {
                $file = $this->request->getUploadedFiles()['templateFile'] ?? null;
                if (!$file instanceof UploadedFile || $file->getError() === UPLOAD_ERR_NO_FILE) {
                    throw new AiException('Изберете файл.');
                }
                if ($file->getError() !== UPLOAD_ERR_OK) {
                    throw new AiException('Файлът не може да бъде качен. Лимитът на сървъра е ' . ini_get('upload_max_filesize') . ' (upload_max_filesize и post_max_size в php.ini).');
                }

                $tmp = tempnam(sys_get_temp_dir(), 'aitpl');
                $file->moveTo($tmp);
                $id = $importer->fromFile($name, $tags, $tmp, (string) $file->getClientFilename(), $this->userId());
            } else {
                throw new AiException('Изберете начин на импорт.');
            }
        } catch (AiException $e) {
            return $this->redirect($this->url('design-templates'), Redactor::redact($e->getMessage()), 'danger');
        } finally {
            if ($tmp !== null && is_file($tmp)) {
                unlink($tmp);
            }
        }

        return $this->redirect($this->url('design-templates', 'view', (string) $id), 'Шаблонът е добавен.');
    }

    public function viewAction()
    {
        $id = (int) $this->request->getParam('id', 0);
        $template = $id > 0 ? $this->ai()->templates()->find($id) : null;
        if ($template === null) {
            throw new CoreException('Записът не е намерен', 404);
        }

        $preview = null;
        if ($template['html'] !== null) {
            $repository = $this->ai()->templates();
            $preview = PreviewDocument::wrap($template['hasPackage'] && $template['entry'] !== null
                ? TemplatePreview::render($template['html'], $template['entry'], fn (string $p) => $repository->read($id, $p))
                : HtmlSanitizer::sanitize($template['html']));
        }

        $files = $template['hasPackage'] ? $this->ai()->templates()->files($id) : [];

        return [
            'form' => new Form($this->formPath('knowledge')),
            'template' => $template,
            'preview' => $preview,
            'fileCount' => count($files),
            'scriptCount' => count(array_filter($files, static fn (string $f): bool => str_ends_with(strtolower($f), '.js'))),
            'newDesignUrl' => $this->url('design', 'index', null, ['template' => $id]),
            'layoutUrl' => $template['html'] === null ? null : $this->url('design-layout', 'index', (string) $id, ['source' => 'template']),
            'deleteUrl' => $this->url('design-templates', 'delete'),
            'homeUrl' => $this->url('design-templates'),
        ];
    }

    public function deleteAction()
    {
        $post = $this->post();

        if ($this->request->isPost() && (new Form($this->formPath('knowledge')))->isValid($post) && ctype_digit((string) ($post['id'] ?? ''))) {
            $this->ai()->templates()->delete((int) $post['id']);

            return $this->redirect($this->url('design-templates'), 'Шаблонът е изтрит.');
        }

        return $this->redirect($this->url('design-templates'));
    }

    /** Таблицата може да липсва (миграция 016): страницата да се покаже с подсказка, а не да се чупи. */
    private function tableRead(\Closure $read): array|false
    {
        try {
            return $read();
        } catch (\Throwable) {
            return false;
        }
    }
}
