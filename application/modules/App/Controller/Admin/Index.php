<?php

namespace App\Controller\Admin;

use Micro\Http\Response\RedirectResponse;
use Micro\Application\Controller;
use Micro\Acl\AuthorizeInterface;
use Micro\Database\Adapter\AdapterAbstract;
use Micro\JWT\JWT;

class Index extends Controller implements AuthorizeInterface
{
    public $scope = 'admin';

    public function indexAction()
    {
        if (!identity()) {
            return new RedirectResponse(route('admin-login'));
        }
    }

    public function slugAction()
    {
        $name = $this->request->getParam('name');

        if (!empty($name)) {
            return \str_slug($name);
        }

        return '';
    }

    public function dbAction()
    {
        $query = '';
        $error = '';
        $rows = [];
        $rowCount = null;

        if ($this->request->isPost()) {
            $db = app('db');
            $query = $this->request->getPost('query');
            try {
                if ($db instanceof AdapterAbstract) {
                    if (preg_match('~^select~ius', $query)) {
                        $rows = $db->fetchAll($query);
                    } else {
                        $rowCount = $db->query($query)->rowCount();
                    }
                }
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        }

        return [
            'query' => $query,
            'error' => $error,
            'rows' => $rows,
            'rowCount' => $rowCount,
        ];
    }

    /**
     * @NotAuthorize
     */
    public function similarityAction()
    {
        $text1 = $this->request->getParam('text1');
        $text2 = $this->request->getParam('text2');
        $similarity = null;

        if ($text1 && $text2) {
            $text1u = mb_strtoupper($text1);
            $text2u = mb_strtoupper($text2);
            $compare = new \App\Helper\Similarity\Compare();
            $similarity = $compare->all($text1u, $text2u);
        }

        return [
            'text1' => $text1,
            'text2' => $text2,
            'similarity' => $similarity,
        ];
    }

    public function accessTokenAction()
    {
        $payload = [
            'time' => time(),
            'username' => identity()->getUsername(),
            'exp' => time() + config('jwt.exp', 0),
            'nbf' => time(),
            'iat' => time(),
        ];

        return [
            'token' => (new JWT())->encode($payload, config('jwt.key')),
        ];
    }
}
