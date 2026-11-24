<?php

namespace AiAdmin\Controller\Admin;

use Ai\Design\LayoutMerger;
use Ai\Design\LayoutWriter;
use Ai\Exception\AiException;
use Ai\Security\Redactor;
use AiAdmin\Service\BaseController;
use Micro\Exception\Exception as CoreException;
use Micro\Form\Form;
use Micro\Http\Response;

/**
 * Превръща макет или шаблон в layout за сайта (frontend). Единственото, което се записва в приложението, е
 * application/resources/layouts/<име>/default.phtml и файловете му в public/templates/<име>/. Админът не се пипа.
 */
class DesignLayout extends BaseController
{
    public function indexAction()
    {
        // id идва от пътя при отваряне на страницата и от скритото поле при изпращане на формуляра.
        $in = $this->request->isPost() ? $this->post() : $this->request->getQueryParams() + ['id' => $this->request->getParam('id', 0)];
        $name = trim((string) ($in['name'] ?? 'default'));
        $selector = trim((string) ($in['selector'] ?? ''));

        $source = $this->source($in);

        $result = null;
        if ($this->request->isPost() && (new Form($this->formPath('knowledge')))->isValid($in)) {
            try {
                $result = $this->build($source, $name, $selector);
            } catch (AiException $e) {
                flash()->setMessage(Redactor::redact($e->getMessage()), 'danger');
            }
        }

        return [
            'form' => new Form($this->formPath('knowledge')),
            'source' => $source,
            'name' => $name,
            'selector' => $selector,
            'overwrite' => !empty($in['overwrite']),
            'overwriteAssets' => !empty($in['overwriteAssets']),
            'force' => !empty($in['force']),
            'result' => $result,
            'previewUrl' => $this->url('design-layout', 'index'),
            'downloadUrl' => $this->url('design-layout', 'download'),
            'writeUrl' => $this->url('design-layout', 'write'),
            'backUrl' => $source['backUrl'],
        ];
    }

    public function downloadAction()
    {
        $in = $this->post();
        $source = $this->source($in);
        if (!$this->request->isPost() || !(new Form($this->formPath('knowledge')))->isValid($in)) {
            return $this->redirect($this->indexUrl($source));
        }

        try {
            $result = $this->build($source, trim((string) ($in['name'] ?? '')), trim((string) ($in['selector'] ?? '')));
        } catch (AiException $e) {
            return $this->redirect($this->indexUrl($source), Redactor::redact($e->getMessage()), 'danger');
        }

        $response = (new Response(null, 200))
            ->withHeader('Content-Type', 'text/plain; charset=utf-8')
            ->withHeader('Content-Disposition', 'attachment; filename="default.phtml"');
        $response->getBody()->write($result['code']);

        return $response;
    }

    public function writeAction()
    {
        $in = $this->post();
        $source = $this->source($in);
        if (!$this->request->isPost() || !(new Form($this->formPath('knowledge')))->isValid($in)) {
            return $this->redirect($this->indexUrl($source));
        }

        $name = trim((string) ($in['name'] ?? ''));
        $overwrite = !empty($in['overwrite']);

        try {
            $result = $this->build($source, $name, trim((string) ($in['selector'] ?? '')));
            $writer = $this->ai()->layoutWriter();

            // PHP, добавен ръчно, който не може да се върне на място, не се забравя тихо.
            $unplaced = $result['merge']['unplaced'] ?? [];
            if ($unplaced !== [] && empty($in['force'])) {
                throw new AiException(sprintf(
                    'В текущия layout има ръчно добавен PHP (%d блока), който не може да се върне на място в новия HTML: %s. Добавете го ръчно след записа или отбележете „Запиши въпреки това“ (старият файл се пази като резервно копие).',
                    count($unplaced),
                    implode(' | ', array_slice($unplaced, 0, 3)),
                ));
            }

            // Проверка преди първия запис: при отказ не остават наполовина копирани файлове.
            $writer->assertWritable($name, $overwrite);
            $assets = ['copied' => 0, 'skipped' => []];
            if ($result['vendor'] !== [] || $result['files'] !== [] || $source['files'] !== []) {
                // CSS и JS файловете се презаписват само по изрично искане: те може да са редактирани ръчно.
                $assets = $writer->copyAssets($name, $source['packageDir'], $source['files'], $result['vendor'], !empty($in['overwriteAssets']), $result['files']);
            }
            $path = $writer->write($name, $result['code'], $overwrite);
        } catch (AiException $e) {
            return $this->redirect($this->indexUrl($source), Redactor::redact($e->getMessage()), 'danger');
        }

        $message = sprintf('Записано: application/resources/layouts/%s', $path);
        if ($assets['copied'] > 0) {
            $message .= sprintf(' и %d файла в public/templates/%s/', $assets['copied'], $name);
        }
        if ($result['merge'] !== null) {
            $message .= sprintf('. Запазен PHP от стария файл: %d блока; старият файл е в data/design/backups/', $result['merge']['preserved']);
        }
        if ($assets['skipped'] !== []) {
            $message .= sprintf('. Не са презаписани вече съществуващите файлове (%d): отбележете „Презапиши CSS и JS“, за да се обновят', count($assets['skipped']));
        }

        return $this->redirect(
            $this->indexUrl($source, ['name' => $name]),
            $message . sprintf('. Ползване във view: $this->extend(\'shared::layouts/%s/default\').', $name),
        );
    }

    /**
     * Откъде идва HTML: версия на макет или шаблон от библиотеката.
     *
     * @param array<string, mixed> $in
     * @return array{type: string, id: int, version: int, title: string, html: string, entry: string, files: list<string>, packageDir: ?string, backUrl: string}
     */
    private function source(array $in): array
    {
        $type = (string) ($in['source'] ?? '');
        $id = (int) ($in['id'] ?? 0);
        $version = (int) ($in['v'] ?? 0);

        if ($type === 'design' && $id > 0) {
            $design = $this->ai()->designs()->find($id);
            $current = $design === null ? null : $this->ai()->designs()->version($id, $version > 0 ? $version : ($this->ai()->designs()->latestVersion($id) ?? 0));
            if ($design === null || $current === null) {
                throw new CoreException('Записът не е намерен', 404);
            }

            return [
                'type' => 'design', 'id' => $id, 'version' => $current['version'], 'title' => $design['title'] . ' (версия ' . $current['version'] . ')',
                'html' => $current['html'], 'entry' => '', 'files' => [], 'packageDir' => null,
                'backUrl' => $this->url('design', 'view', (string) $id, ['v' => $current['version']]),
            ];
        }

        if ($type === 'template' && $id > 0) {
            $template = $this->ai()->templates()->find($id);
            if ($template === null) {
                throw new CoreException('Записът не е намерен', 404);
            }
            if ($template['html'] === null) {
                throw new CoreException('Шаблонът е само описание (от адрес) и не може да стане layout.', 404);
            }

            return [
                'type' => 'template', 'id' => $id, 'version' => 0, 'title' => $template['name'],
                'html' => $template['html'], 'entry' => (string) $template['entry'],
                'files' => $template['hasPackage'] ? $this->ai()->templates()->files($id) : [],
                'packageDir' => $template['hasPackage'] ? $this->ai()->templates()->packageDir($id) : null,
                'backUrl' => $this->url('design-templates', 'view', (string) $id),
            ];
        }

        throw new CoreException('Записът не е намерен', 404);
    }

    /**
     * @param array<string, mixed> $source
     * @return array{code: string, report: list<string>, vendor: array<string, string>, files: array<string, string>, merge: ?array{preserved: int, replaced: int, unplaced: list<string>}}
     */
    private function build(array $source, string $name, string $selector): array
    {
        LayoutWriter::assertName($name);

        $result = $this->ai()->layoutBuilder()->build($source['html'], $name, $selector, $source['entry'], array_fill_keys($source['files'], true));
        $result['merge'] = null;

        // Ако layout-ът вече е създаден от дизайнера, само HTML-ът се обновява, а ръчно добавеният PHP се връща на мястото си.
        $existing = $this->ai()->layoutWriter()->existingCode($name);
        if ($existing !== null) {
            $merge = (new LayoutMerger())->merge($existing, $result['code']);
            $result['code'] = $merge['code'];
            $result['merge'] = ['preserved' => $merge['preserved'], 'replaced' => $merge['replaced'], 'unplaced' => $merge['unplaced']];
        }

        return $result;
    }

    /** @param array<string, mixed> $source */
    private function indexUrl(array $source, array $extra = []): string
    {
        return $this->url('design-layout', 'index', (string) $source['id'], ['source' => $source['type'], 'v' => $source['version']] + $extra);
    }
}
