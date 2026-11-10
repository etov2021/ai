<?php

namespace App\Controller\Admin;

use Micro\Application\Controller;
use Micro\Http\Response\RedirectResponse;
use Micro\Application\Utils;
use Micro\Http\Response\JsonResponse;
use Micro\Acl\AuthorizeInterface;
use App\Table\Settings as SettingsTable;
use App\Table\SettingsSections;

class Settings extends Controller implements AuthorizeInterface
{
    public $scope = 'admin';

    public function indexAction()
    {
        $settingsTable = new SettingsTable();
        $errors = [];
        $post = [];

        if ($this->request->isPost()) {

            $post = $this->request->getPost();
            $post = Utils::arrayMapRecursive('trim', $post);

            if (isset($post['btnAdd'])) {
                if (empty($post['key'])) {
                    $errors['key'] = translate('Полето е задължително');
                } else {
                    if ($settingsTable->fetchRow(['`key` = ?' => $post['key'], '`domainId` = ?' => DOMAIN])) {
                        $errors['key'] = translate('Записът съществува');
                    } else {
                        $data = [
                            'domainId' => DOMAIN,
                            'key' => $post['key'],
                            'value' => $post['value'],
                            'sectionId' => $post['section'],
                            'elementType' => $post['elementType'],
                        ];
                        $settingsTable->createRow($data)->save();
                        SettingsTable::removeCache();
                        return (new RedirectResponse(route()))->withFlash('Информацията е записана');
                    }
                }
            }

            if (isset($post['btnSave'])) {
                $data = isset($post['settings']) ? $post['settings'] : [];
                try {
                    $settingsTable->getAdapter()->beginTransaction();
                    foreach ($data as $k => $v) {
                        $settingsTable->update(['value' => $v], ['id = ?' => $k]);
                    }
                    SettingsTable::removeCache();
                    $settingsTable->getAdapter()->commit();
                } catch (\Exception $e) {
                    $settingsTable->getAdapter()->rollBack();
                }
                return (new RedirectResponse(route()))->withFlash('Информацията е записана');
            }
        }

        return [
            'settings' => $settingsTable->getSettings(),
            'settingsSections' => (new SettingsSections())->fetchSections(),
            'errors' => $errors,
            'post' => $post
        ];
    }

    public function deleteAction()
    {
        $id = (int) $this->request->getParam('id');

        (new SettingsTable())->delete(['id = ?' => $id]);

        return new JsonResponse(['success' => 1]);
    }
}
