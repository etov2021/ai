<?php

namespace Micro\Form\Element;

use Micro\Form\Element;
use Micro\Http\UploadedFile;
use Micro\Validate;
use Micro\Validate\File\FileInterface;
use Micro\Http\Request;
use Micro\Validate\File\Upload;
use Micro\Container\SharedContainer;

class File extends Element
{
    /**
     * @var Request
     */
    protected $request;

    protected $count = 1;

    public function setRequest(Request $request)
    {
        $this->request = $request;
    }

    public function setCount($count)
    {
        $count = (int) $count;

        $this->count = $count <= 0 ? 1 : $count;
    }

    public function getCount()
    {
        return $this->count;
    }

    public function render()
    {
        $tmp = '';

        for ($i = 0; $i < $this->count; ++$i) {
            $tmp .= '<input type="file" name="' . $this->getFullyName() . '"' . $this->htmlAttributes() . ' />';
        }

        if ($this->showErrors) {
            $tmp .= $this->renderErrors();
        }

        return $tmp;
    }

    public function isValid($value, ?array $context = \null)
    {
        $request = $this->request ?: SharedContainer::getInstance()->get('request');

        if (!$request instanceof Request) {
            return \true;
        }

        $uploadedFiles = $request->getUploadedFiles();

        if ($this->belongsTo) {
            $files = isset($uploadedFiles[$this->belongsTo]) && isset($uploadedFiles[$this->belongsTo][$this->getName()])
                     ? $uploadedFiles[$this->belongsTo][$this->getName()]
                     : [];
        } else {
            $files = isset($uploadedFiles[$this->getName()])
                     ? $uploadedFiles[$this->getName()]
                     : [];
        }

        if (!empty($files)) {
            $this->setValue($files);
        }

        if (!\is_array($files)) {
            $files = [$files];
        }

        if (!$this->hasValidator(Validate\File\Upload::class)) {
            $this->prependValidator(new Validate\File\Upload());
        }

        $validators = $this->getValidators();

        foreach ($files as $file) {
            if ($file instanceof UploadedFile) {
                foreach ($validators as $key => $validator) {
                    if ($validator instanceof Upload) {
                        $validator->setRequired($this->required);
                    }
                    if ($validator instanceof FileInterface && !$validator->isValid($file)) {
                        foreach ($validator->getMessages() as $message) {
                            $this->addError($message);
                        }
                        if ($this->breakValidatorChain) {
                            break 2;
                        }
                    }
                }
            }
        }

        if ($this->hasErrors()) {
            return \false;
        }

        return \true;
    }
}