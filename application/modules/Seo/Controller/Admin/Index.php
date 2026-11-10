<?php

namespace Seo\Controller\Admin;

use Micro\Application\Controller;
use Seo\Model\Meta;
use Micro\Acl\AuthorizeInterface;
use Micro\View\View;
use Micro\Application\Utils;
use Micro\Http\Response\RedirectResponse;
use Seo\Model\Table\Redirects;
use Micro\Cache\Cache;

class Index extends Controller implements AuthorizeInterface
{
    public function redirectsAction()
    {
        $seoRedirects = new Redirects();

        if ($this->request->isPost()) {

            $redirects = $this->request->getPost('redirects', []);
            $redirects = Utils::arrayMapRecursive('trim', $redirects, true);

            try {
                $seoRedirects->setRedirects($redirects);
                return (new RedirectResponse(route()))->withFlash('Информацията е записана');
            } catch (\Exception $e) {
                return (new RedirectResponse(route()))->withFlash($e->getMessage(), 'danger');
            }

        }

        return new View('admin/index/redirects', [
            'items' => $seoRedirects->getRedirects(),
        ]);
    }

    public function saveAction()
    {
        $target = $this->request->getParam('target');
        $targetId = $this->request->getParam('targetId');
        $title = trim($this->request->getParam('__metaTitle'));
        $description = trim($this->request->getParam('__metaDescription'));

        $model = new Meta();
        $model->useCurrentLanguage();

        $item = $model->getAlwaysMetaItem($target, $targetId);

        if (empty($title) && empty($description) && $item['id']) {
            $model->delete($item);
        } else {
            $item->setFromArray(['title' => $title,'description' => $description]);
            $model->save($item);
        }

        $cache = cache('json');
        if ($cache && $target) {
            $cache->clean(Cache::CLEANING_MODE_MATCHING_TAG, ['Meta_' . $target]);
        }

        return '';
    }
}