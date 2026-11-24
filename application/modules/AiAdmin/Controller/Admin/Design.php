<?php

namespace AiAdmin\Controller\Admin;

use Ai\Design\DesignBrief;
use Ai\Design\PreviewDocument;
use Ai\Exception\AiException;
use Ai\Security\Redactor;
use AiAdmin\Service\BaseController;
use Micro\Exception\Exception as CoreException;
use Micro\Form\Form;

class Design extends BaseController
{
    public function indexAction()
    {
        $rows = $this->tableRead(fn () => $this->ai()->designs()->all());
        $templates = $this->tableRead(fn () => $this->ai()->templates()->all());

        return [
            'form' => new Form($this->formPath('knowledge')),
            'rows' => array_map(fn (array $r) => ['item' => $r, 'url' => $this->url('design', 'view', (string) $r['id'])], $rows ?: []),
            'missingTable' => $rows === false,
            'templateOptions' => array_column($templates ?: [], 'name', 'id'),
            'selectedTemplate' => (string) ($this->request->getQueryParams()['template'] ?? ''),
            'stacks' => DesignBrief::STACKS,
            'templatesUrl' => $this->url('design-templates'),
            'agents' => $this->agentOptions(),
            'defaultAgent' => (string) config('ai.design.agent', ''),
            'screens' => DesignBrief::SCREENS,
            'styles' => DesignBrief::STYLES,
            'themes' => DesignBrief::THEMES,
            'fonts' => ['sans' => 'Без засечки (system-ui)', 'serif' => 'Със засечки (Georgia)', 'mono' => 'Моноширинен'],
            'langs' => DesignBrief::LANGS,
            'referenceModes' => DesignBrief::REFERENCE_MODES,
            'createUrl' => $this->url('design', 'create'),
            'homeUrl' => $this->url('index'),
        ];
    }

    public function createAction()
    {
        $post = $this->post();
        if (!$this->request->isPost() || !(new Form($this->formPath('knowledge')))->isValid($post)) {
            return $this->redirect($this->url('design'));
        }

        @set_time_limit(300);

        try {
            $result = $this->ai()->designService()->create(
                DesignBrief::fromInput($post),
                $this->agentCode($post),
                $this->userId(),
            );
        } catch (AiException $e) {
            return $this->redirect($this->url('design'), Redactor::redact($e->getMessage()), 'danger');
        }

        return $this->done($result, 'Макетът е създаден.');
    }

    public function viewAction()
    {
        $id = (int) $this->request->getParam('id', 0);
        $design = $id > 0 ? $this->ai()->designs()->find($id) : null;
        if ($design === null) {
            throw new CoreException('Записът не е намерен', 404);
        }

        $versions = $this->ai()->designs()->versions($id);
        $requested = (int) ($this->request->getQueryParams()['v'] ?? 0);
        $number = $requested > 0 ? $requested : ($versions[0]['version'] ?? 0);
        $current = $this->ai()->designs()->version($id, $number);
        if ($current === null) {
            throw new CoreException('Записът не е намерен', 404);
        }

        return [
            'form' => new Form($this->formPath('knowledge')),
            'design' => $design,
            'current' => $current,
            'preview' => $design['brief']->stack === 'bootstrap5'
                ? PreviewDocument::wrap($current['html'], server_url(), server_url(base_url()) . '/')
                : PreviewDocument::wrap($current['html']),
            // id е в пътя, а не в заявката: url() не позволява ключът id в $query да замести параметъра id.
            'layoutUrl' => $this->url('design-layout', 'index', (string) $id, ['source' => 'design', 'v' => $current['version']]),
            'versions' => array_map(fn (array $v) => $v + ['url' => $this->url('design', 'view', (string) $id, ['v' => $v['version']])], $versions),
            'brief' => $design['brief'],
            'agents' => $this->agentOptions(),
            'defaultAgent' => (string) config('ai.design.agent', ''),
            'id' => $id,
            'refineUrl' => $this->url('design', 'refine'),
            'deleteUrl' => $this->url('design', 'delete'),
            'homeUrl' => $this->url('design'),
        ];
    }

    public function refineAction()
    {
        $post = $this->post();
        $id = ctype_digit((string) ($post['id'] ?? '')) ? (int) $post['id'] : 0;
        if (!$this->request->isPost() || !(new Form($this->formPath('knowledge')))->isValid($post) || $id < 1) {
            return $this->redirect($this->url('design'));
        }

        @set_time_limit(300);

        try {
            $result = $this->ai()->designService()->refine($id, (string) ($post['instruction'] ?? ''), $this->agentCode($post), $this->userId());
        } catch (AiException $e) {
            return $this->redirect($this->url('design', 'view', (string) $id), Redactor::redact($e->getMessage()), 'danger');
        }

        return $this->done($result, 'Създадена е нова версия.');
    }

    public function deleteAction()
    {
        $post = $this->post();

        if ($this->request->isPost() && (new Form($this->formPath('knowledge')))->isValid($post) && ctype_digit((string) ($post['id'] ?? ''))) {
            $this->ai()->designs()->delete((int) $post['id']);

            return $this->redirect($this->url('design'), 'Макетът е изтрит.');
        }

        return $this->redirect($this->url('design'));
    }

    /** @param array{designId: int, version: int, warning: ?string} $result */
    private function done(array $result, string $message)
    {
        return $this->redirect(
            $this->url('design', 'view', (string) $result['designId'], ['v' => $result['version']]),
            $result['warning'] ?? $message,
            $result['warning'] === null ? 'success' : 'warning',
        );
    }

    /** Таблицата може да липсва (миграция 015): страницата да се покаже с подсказка, а не да се чупи. */
    private function tableRead(\Closure $read): array|false
    {
        try {
            return $read();
        } catch (\Throwable) {
            return false;
        }
    }
}
