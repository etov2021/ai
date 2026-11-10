<?php

namespace App\Controller\Admin;

use Micro\Application\Controller;
use Micro\Acl\AuthorizeInterface;
use Micro\Application\Utils;
use Micro\Http\Response\JsonResponse;
use Micro\Cache as MicroCache;

class Cache extends Controller implements AuthorizeInterface
{
    public $scope = 'admin';

    public function indexAction()
    {
        $tags = $this->getTags();

        ksort($tags);

        return ['tags' => $tags];
    }

    public function deleteTagAction()
    {
        $tag = $this->request->getParam('tag');

        try {
            foreach (MicroCache\Cache::loadAll() as $cache) {
                $cache->clean(MicroCache\Cache::CLEANING_MODE_MATCHING_TAG, [$tag]);
            }
        } catch (\Exception $e) {

        }

        return new JsonResponse(['success' => 1]);
    }

    public function deleteTagFileAction()
    {
        $file = $this->request->getParam('file');

        if ($file) {
            $file = Utils::base64urlDecode($file);
            if (is_file($file)) {
                @unlink($file);
            }
        }

        return new JsonResponse(['success' => 1]);
    }

    public function viewFileAction()
    {
        $file = $this->request->getParam('file');

        $content = '';

        if ($file) {
            $file = Utils::base64urlDecode($file);
            if (is_file($file)) {
                $fileContent = file_get_contents($file);
                $content = @json_decode($fileContent, true);
                if (empty($content)) {
                    $content = @unserialize($fileContent);
                    if (empty($content)) {
                        if (is_scalar($fileContent)) {
                            $content = [$fileContent];
                        }
                    }
                }
            }
        }

        return ['content' => $content];
    }

    protected function getTags()
    {
        $tags = [];

        foreach (Utils::globRecursive(data_path() . '/*') as $file) {

            if (!preg_match('~' . preg_quote('---internal-metadatas') . '$~', $file)) {
                continue;
            }

            $metaContent = @unserialize(file_get_contents($file));

            if (is_array($metaContent) && isset($metaContent['tags'])) {
                foreach ($metaContent['tags'] as $tag) {
                    if (!isset($tags[$tag])) {
                        $tags[$tag] = [];
                    }
                    $fileContent = realpath(str_replace('---internal-metadatas', '', $file));
                    $tags[$tag][] = [
                        'file' => realpath($file),
                        'fileContent' => ($fileContent ?: null),
                        'size' => ($fileContent ? filesize($fileContent) : 0),
                        'meta' => $metaContent,
                    ];
                }
            }
        }

        return $tags;
    }
}
