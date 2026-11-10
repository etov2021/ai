<?php

namespace Files\Controller\Admin;

use Micro\Application\Controller;
use Micro\Acl\AuthorizeInterface;
use Files\Table\Files;
use Micro\Http\UploadedFile;

class Storage extends Controller implements AuthorizeInterface
{
    public $scope = 'admin';

    public function indexAction()
    {
        $identity = identity();
        $table = new Files();

        $files = [];

        $storage = $table->getFiles('Files_Storage', null, 'file', null, null, ['order' => 'id DESC']);
        foreach ($storage as $file) {
            $files[] = $file;
        }

        return [
            'files' => $files,
        ];
    }

    public function uploadFilesAction()
    {
        if ($this->request->contentLengthExceed($length, $max)) {
            throw new \Exception(sprintf('Съдържанието (%s байта) което се опитвате да изпратите е по-голямо от post_max_size (%s байта)', $length, $max), 500);
        }

        $identity = identity();

        $type = 'file';
        $target = 'Files_Storage';
        $targetId = 0;

        $table = new Files();

        $uploadedFiles = $this->request->getUploadedFiles();

        $sizeForUpload = 0;
        $files = [];
        if (isset($uploadedFiles[$type])) {
            foreach ((array) $uploadedFiles[$type] as $file) {
                if ($file instanceof UploadedFile && !$file->getError()) {
                    $sizeForUpload += $file->getSize();
                    $files[] = $file;
                }
            }
        }

        $sumSize = $table->getAdapter()->fetchOne('SELECT SUM(size) FROM Files WHERE target = "Files_Storage" AND `type` = "file"');
        $fileStorageMaxSize = settings('fileStorageMaxSize', 100);
        $sizeForUpload = (($sizeForUpload + $sumSize) / 1024 / 1024);

        if ($sizeForUpload > $fileStorageMaxSize) {
            throw new \Exception(sprintf('Максималният размер на хранилището е достигнато (%sMB).', $fileStorageMaxSize), 500);
        }

        foreach ($files as $file) {
            $table->add($target, $targetId, $file, $type);
        }

        return '';
    }

    public function deleteFileAction()
    {
        $id = $this->request->getParam('id');

        if ($id) {
            $id = (array) $id;
            $filesModel = new Files();
            $identity = identity();
            foreach ($id as $i) {
                $fileRow = $filesModel->find((int) $i)->current();
                if ($fileRow) {
                    $filesModel->remove($i);
                }
            }
        }

        return '';
    }

    public function ajaxChangeNameAction()
    {
        $id = (int) $this->request->getParam('id');
        $value = $this->request->getParam('value');

        $file = (new Files())->find($id)->current();

        if (empty($file)) {
            raise('Невалиден файл');
        }

        $file->name = $value;
        $file->save();

        return $file->name;
    }
}