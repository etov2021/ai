<?php

namespace AiAdmin\Controller\Admin;

use Ai\Exception\AiException;
use Ai\File\FileAnalyzer;
use Ai\Security\Redactor;
use AiAdmin\Service\BaseController;
use Micro\Form\Form;
use Micro\Http\UploadedFile;

class Documents extends BaseController
{
    private const UPLOAD_ERRORS = [
        UPLOAD_ERR_INI_SIZE => 'Файлът надвишава лимита на сървъра (upload_max_filesize в php.ini).',
        UPLOAD_ERR_FORM_SIZE => 'Файлът е твърде голям.',
        UPLOAD_ERR_PARTIAL => 'Файлът е качен непълно. Опитайте отново.',
        UPLOAD_ERR_NO_FILE => 'Изберете файл.',
    ];

    public function indexAction()
    {
        $agents = $this->safely(fn () => array_values(array_filter($this->ai()->agents()->all(), fn ($a) => $a->runnable)));

        $form = new Form($this->formPath('documents'));
        $options = [];
        foreach ($agents as $a) {
            $options[$a->code] = $a->name;
        }
        $form->getElement('agent')->setMultiOptions($options);
        $form->getElement('mode')->setMultiOptions([
            FileAnalyzer::SUMMARY => 'Обобщение',
            FileAnalyzer::EXTRACT => 'Извличане на данни',
        ]);

        $result = null;
        $fileName = '';
        $mode = '';

        if ($this->request->isPost()) {
            $post = $this->post();

            if ($form->isValid($post)) {
                $upload = $this->request->getUploadedFiles()['document'] ?? null;

                if (!$upload instanceof UploadedFile || $upload->getError() !== UPLOAD_ERR_OK) {
                    $error = $upload instanceof UploadedFile ? $upload->getError() : UPLOAD_ERR_NO_FILE;
                    flash()->setMessage(self::UPLOAD_ERRORS[$error] ?? 'Файлът не може да бъде качен.', 'danger');
                } else {
                    // Името от клиента е само за показване и за разширението; на диска файлът е с генерирано име и се трие веднага.
                    $fileName = (string) $upload->getClientFilename();
                    $mode = (string) $post['mode'];
                    $tmp = tempnam(sys_get_temp_dir(), 'aidoc');

                    try {
                        $upload->moveTo($tmp);
                        $result = $this->ai()->fileAnalyzer()->analyze(
                            $tmp,
                            $fileName,
                            (string) $post['agent'],
                            (string) $post['mode'],
                            (string) ($post['fields'] ?? ''),
                            $this->userId(),
                        );
                    } catch (AiException $e) {
                        flash()->setMessage(Redactor::redact($e->getMessage()), 'danger');
                    } finally {
                        if (is_file($tmp)) {
                            unlink($tmp);
                        }
                    }
                }
            }
        }

        return ['form' => $form, 'result' => $result, 'fileName' => $fileName, 'mode' => $mode, 'homeUrl' => $this->url('index')];
    }
}
