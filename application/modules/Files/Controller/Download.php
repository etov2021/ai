<?php

namespace Files\Controller;

use Micro\Application\Controller;
use Micro\Http\Response\FileResponse;
use Files\Table\Files;

class Download extends Controller
{
    public function indexAction()
    {
        $hash = (string) $this->request->getParam('hash');

        if (!empty($hash)) {

            $filesModel = new Files();

            $file = $filesModel->getFileByHash($hash, ['file', 'name', 'target', 'customName', 'extension']);

            if ($file !== null) {
                if ($file['customName']) {
                    $name = $file['customName'] . '.' . $file['extension'];
                } else {
                    $name = $file['name'];
                }
                return new FileResponse(public_path(Files::getDir() . DIRECTORY_SEPARATOR . $file['target'] . DIRECTORY_SEPARATOR . $file['file']), $name);
            }
        }

        return new FileResponse(null);
    }
}