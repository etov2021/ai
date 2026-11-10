<?php

namespace Files\Controller\Admin;

use Micro\Application\Controller;
use Files\Table\Files;
use Micro\Acl\AuthorizeInterface;
use Micro\Http\UploadedFile;
use Micro\View\View;
use Micro\Application\Utils;
use Files\Helper\Files as HelperFiles;
use Micro\Container\SharedContainer;
use Files\Table\FilesTags;
use Micro\Http\Response\JsonResponse;
use Micro\Http\Response\FileResponse;

class Index extends Controller implements AuthorizeInterface
{
    public $scope = 'admin';

    public function uploadFilesFromPublicAction()
    {
        $tag      = $this->request->getParam('tag',null);
        $type     = $this->request->getPost('type');
        $files    = (array) $this->request->getPost('files', []);
        $target   = $this->request->getParam('target');
        $targetId = $this->request->getParam('targetId');
        $settings = $this->request->getParam('settings');
        $view     = $this->request->getParam('view', 'admin::tab/_ajax-table-files');

        if ($settings) {
            $settings = json_decode($settings, true);
            $settings = is_array($settings) ? $settings : [];
        } else {
            $settings = [];
        }

        $filesModel = new Files();

        $mimeTypes = Utils::getMimeTypes();

        $lang = null;
        if (isset($settings[$type]) && isset($settings[$type]['language_dependent']) && $settings[$type]['language_dependent']) {
            $lang = SharedContainer::getInstance()->get('current_language')->getId();
        }
        $tags = [];
        if (isset($settings[$type]) && isset($settings[$type]['useTags'])) {
            $fileTags = new FilesTags();
            $tags = $fileTags->getTags( $target);
        }

        $fileRows = [];

        foreach ($files as $file) {
            if (strpos($file, '..') !== false) {
                continue;
            }
            $file = realpath(public_path(str_replace(base_url(), '', $file)));
            if ($file) {
                $pathinfo = pathinfo($file);
                $mimeType = 'application/octet-stream';
                if (isset($mimeTypes[$pathinfo['extension']])) {
                    $mimeType = $mimeTypes[$pathinfo['extension']];
                } else if (function_exists('mime_content_type')) {
                    $mimeType = mime_content_type($file);
                }
                $uploadedFile = new UploadedFile(
                    $file,
                    $pathinfo['basename'],
                    $mimeType,
                    filesize($file)
                );
                $fileRow = $filesModel->add($target, $targetId, $uploadedFile, $type, $lang, $tag);
                if ($settings && isset($settings[$type])) {
                    HelperFiles::resize($fileRow, $settings[$type]);
                }
                $fileRows[] = $fileRow;
            }
        }

        trigger('Upload.Files', [
            'target' => $target,
            'targetId' => $targetId,
            'type' => $type,
            'files' => $fileRows,
        ]);

        if ($view) {
            return new View($view, [
                'files' => $filesModel->getFiles($target, $targetId, $type, $lang, $tag),
                'useTags'=>isset($settings[$type]['useTags']) ? 1 : 0,
                'tags' => $tags
            ]);
        }

        return '';
    }

    public function uploadFilesAction()
    {
        if ($this->request->contentLengthExceed($length, $max)) {
            throw new \Exception(sprintf('Съдържанието (%s байта) което се опитвате да изпратите е по-голямо от post_max_size (%s байта)', $length, $max), 500);
        }

        $tag      = $this->request->getParam('tag', null);
        $type     = $this->request->getParam('type');
        $target   = $this->request->getParam('target');
        $targetId = $this->request->getParam('targetId');
        $settings = $this->request->getParam('settings');
        $view     = $this->request->getParam('view', 'admin::tab/_ajax-table-files');

        if ($settings) {
            $settings = json_decode($settings, true);
            $settings = is_array($settings) ? $settings : [];
        } else {
            $settings = [];
        }

        $filesModel = new Files();

        $uploadedFiles = $this->request->getUploadedFiles();

        $lang = null;
        if (isset($settings[$type]) && isset($settings[$type]['language_dependent']) && $settings[$type]['language_dependent']) {
            $lang = SharedContainer::getInstance()->get('current_language')->getId();
        }

        $tags = [];
        if (isset($settings[$type]) && isset($settings[$type]['useTags'])) {
            $fileTags = new FilesTags();
            $tags = $fileTags->getTags( $target, $targetId);
        }

        $link = $this->request->getParam($type);

        $fileRows = [];

        if (isset($uploadedFiles[$type])) {
            foreach ((array) $uploadedFiles[$type] as $file) {
                if ($file instanceof UploadedFile && !$file->getError()) {
                    $fileRow = $filesModel->add($target, $targetId, $file, $type, $lang, $tag);
                    if ($settings && isset($settings[$type])) {
                        HelperFiles::resize($fileRow, $settings[$type]);
                    }
                    $fileRows[] = $fileRow;
                }
            }
        } else if ($link) {
             if (!filter_var($link, FILTER_VALIDATE_URL) === false) {
                throw new \Exception('Invalid link');
             }
             $fileRow = $filesModel->addLink($target, $targetId, $link[0], $type, $lang, $tag);
             $fileRows[] = $fileRow;
        }

        trigger('Upload.Files', [
            'target' => $target,
            'targetId' => $targetId,
            'type' => $type,
            'files' => $fileRows,
        ]);

        if ($view) {
            return new View($view, [
                'files' => $filesModel->getFiles($target, $targetId, $type, $lang, $tag),
                'useTags' => isset($settings[$type]['useTags']) ? 1 : 0,
                'tags' => $tags,
            ]);
        }

        return '';
    }

    public function reorderFilesAction()
    {
        $sort = $this->request->getPost('sort', []);

        if (is_array($sort)) {
            $filesModel = new Files();
            $filesModel->getAdapter()->beginTransaction();
            foreach ($sort as $k => $id) {
                $filesModel->update(['sort' => ($k + 1)], ['id = ?' => $id]);
            }
            $filesModel->removeCache();
            $filesModel->getAdapter()->commit();
        }

        return '';
    }

    public function deleteFileAction()
    {
        $id = $this->request->getParam('id');

        if ($id) {
            $id = (array) $id;
            $filesModel = new Files();
            foreach ($id as $i) {
                $filesModel->remove($i);
            }
        }

        return '';
    }

    public function updateFileAction()
    {
        $id = (int) $this->request->getParam('id');
        $name = $this->request->getParam('name');
        $content = $this->request->getParam('content');
        $tags = $this->request->getParam('tags',null);
        $lang = $this->request->getParam('fileLang');

        $filesModel = new Files();

        if ($lang) {
            $result = $filesModel->updateMetadata($id, [$lang => ['name' => $name, 'content' => $content]], $tags);
        } else {
            $result = $filesModel->updateMetadata($id, ['name' => $name, 'content' => $content], $tags);
        }

        return '';
    }

    public function addTagsAction()
    {
        $tags = $this->request->getParam('tags');
        $target = $this->request->getParam('target');
        $targetId = $this->request->getParam('targetId');

        $filesModel = new FilesTags();

        $filesModel->save($target, $targetId, $tags);

        return new JsonResponse();
    }

    /**
     * @return \Micro\View\View|string
     */
    public function filterTagsAction()
    {
        $tag      = $this->request->getParam('tag');
        $type     = $this->request->getParam('type');
        $target   = $this->request->getParam('target');
        $targetId = $this->request->getParam('targetId');
        $settings = $this->request->getParam('settings');
        $view     = $this->request->getParam('view', 'admin::tab/_ajax-table-files');

        if ($settings) {
            $settings = json_decode($settings, true);
            $settings = is_array($settings) ? $settings : [];
        } else {
            $settings = [];
        }

        $filesModel = new Files();

        $lang = null;
        if (isset($settings[$type]) && isset($settings[$type]['language_dependent']) && $settings[$type]['language_dependent']) {
            $lang = SharedContainer::getInstance()->get('current_language')->getId();
        }

        $tags = [];
        if (isset($settings[$type]) && isset($settings[$type]['useTags'])) {
            $fileTags = new FilesTags();
            $tags = $fileTags->getTags( $target,$targetId);

        }

        if ($view) {
            return new View($view, [
                'files' => $filesModel->getFiles($target, $targetId, $type, $lang, $tag),
                'useTags'=>isset($settings[$type]['useTags']) ? 1 : 0,
                'tags' => $tags,
                'selectedTag' =>$tag
            ]);
        }

        return '';
    }

    public function downloadAction()
    {
        $file = Utils::base64urlDecode($this->request->getParam('file'));

        return new FileResponse(public_path($file));
    }
}