<?php

namespace Files;

use Micro\Application\Module as BaseModule;
use Micro\Container\ContainerInterface;
use Micro\Event\Manager;
use Micro\Event\Message;
use Files\Entity\FileInterface;
use Files\Table\Files;
use Micro\Http\UploadedFile;
use Micro\Form\Element\File as FileElement;
use Files\Helper\Files as HelperFiles;
use Micro\Model\Entity\TranslationInterface;
use Micro\Form\Element\Text as TextElement;
use Micro\Container\SharedContainer;
use Micro\Application\Utils;

class Module extends BaseModule
{
    public function boot(ContainerInterface $container)
    {
        $manager = $container->get('event');

        if (!$manager instanceof Manager) {
            return;
        }

        $manager->attach('*.Model.*.After.Delete', function (Message $message) {

            $entity = $message->entity;

            if (!$entity instanceof FileInterface) {
                return;
            }

            $model = new Files();

            $model->removeByTarget(
                $entity->getTarget(),
                $entity->getTargetId()
            );
        });

        $manager->attach('*.Model.*.After.Save', function (Message $message) {

            $entity = $message->entity;

            if (!$entity instanceof FileInterface) {
                return;
            }

            $fileSettings = $entity->getFileSettings();

            $filesModel = new Files();
            $request = SharedContainer::getInstance()->get('request');

            foreach ($fileSettings as $type => $settings) {

                if (!isset($entity[$type])) {
                    continue;
                }

                $files = $entity[$type];

                $removeAll = false;

                if (!is_array($files)) {
                    $removeAll = true;
                    $files = empty($files) ? [] : [$files];
                }

                $targetId = $entity->getTargetId();
                $target = $entity->getTarget();

                $lang = null;

                if (isset($settings['language_dependent']) && $settings['language_dependent']) {
                    if ($entity instanceof TranslationInterface) {
                        $lang = $entity->getLanguageId();
                    }
                }

                $hasUploadedFiles = false;

                foreach ($files as $file) {
                    if ($file instanceof UploadedFile && !$file->getError()) {
                        $hasUploadedFiles = true;
                        break;
                    }
                }

                if (isset($settings['editor']) && $settings['editor']) {
                    $typeEditor = $request->getParam($type . '-editor');
                    if ($typeEditor) {
                        if (!$hasUploadedFiles) {
                            $file = realpath(public_path(str_replace(base_url(), '', $typeEditor)));
                            if ($file) {
                                $mimeTypes = Utils::getMimeTypes();
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
                                $files[] = $uploadedFile;
                            }
                        } else {

                        }
                    }
                }

                foreach ($files as $file) {

                    if ($file instanceof UploadedFile && !$file->getError()) {

                        if ($removeAll) {
                            $filesModel->removeByTarget($target, $targetId, $type, $lang);
                        }

                        $fileRow = $filesModel->add($target, $targetId, $file, $type, $lang);

                        HelperFiles::resize($fileRow, $settings);
                    }
                }

                if (method_exists($entity, 'getFileNames')) {
                    $fileNames = $entity->getFileNames();
                    if (count($fileNames)) {
                        foreach ($fileNames as $id => $name) {
                            $filesModel->update(['customName' => $name], 'id = ' . $id);
                        }
                    }
                }
            }
        });

        $manager->attach('Crud.Form.Prepare', function (Message $message) {

            $entity = $message->entity;

            if (!$entity instanceof FileInterface) {
                return;
            }

            $fileSettings = $entity->getFileSettings();

            foreach ($fileSettings as $type => $settings) {

                if (!isset($entity[$type])) {
                    continue;
                }

                $settings = $settings + [
                    'label'      => ucfirst($type) . ' file',
                    'required'   => 0,
                    'isArray'    => 0,
                    'attributes' => [],
                    'validators' => [],
                ];

                unset($settings['required']);

                if ($settings['isArray']) {
                    $settings['attributes']['multiple'] = 'multiple';
                }

                $fileElement = new FileElement($type, $settings);

                foreach ($fileElement->getValidators() as $validator) {
                    if ($validator instanceof \Micro\Validate\File\Extension) {
                        if (!empty($validator->getAccept())) {
                            $fileElement->setAttribute('accept', implode(', ', $validator->getAccept()));
                        } else if (!empty($validator->getExtensions())) {
                            $fileElement->setAttribute('accept', implode(', ', $validator->getExtensions()));
                        }
                    }
                }

                $message->form->addElement($fileElement);

                if (isset($settings['editor']) && $settings['editor']) {
                    $fileElementEditor = new TextElement($type . '-editor', ['attributes' => ['id' => 'file-manager-field-' . $type, 'readonly']]);
                    $message->form->addElement($fileElementEditor);
                }
            }
        });

        $manager->attach('Crud.Form.Validate', function (Message $message) {

            $entity = $message->entity;

            if (!$entity instanceof FileInterface) {
                return;
            }

            $fileSettings = $entity->getFileSettings();

            foreach ($fileSettings as $type => $settings) {

                if (!isset($entity[$type])) {
                    continue;
                }

                if (isset($settings['required']) && $settings['required']) {

                    $files = $entity->getFiles($type);

                    if (!empty($files)) {
                        continue;
                    }

                    $file = $message->form->{$type};
                    $fileEditor = $message->form->{$type . '-editor'};

                    if ((!$fileEditor || !$fileEditor->getValue()) && ($file->getValue() instanceof UploadedFile && $file->getValue()->getError())) {
                        $file->addError('Полето е задължително');
                    }
                }
            }
        });
    }

    public function getConfig()
    {
        return [];
    }
}
