<?php

namespace Micro\Form;

use Micro\Validate;
use Micro\Validate\ValidateInterface;
use Micro\Exception\Exception as CoreException;
use Micro\Escaper\Escaper;
use Micro\Validate\ClientValidateInterface;

abstract class Element
{
    protected $name;
    protected $value;
    protected $label;
    protected $labelClass;
    protected $description;
    protected $belongsTo;
    protected $required = \false;
    protected $validators = [];
    protected $errors = [];
    protected $showErrors = \true;
    protected $attributes = [];
    protected $isArray = \false;
    protected $translate = \true;
    protected $translateValue = \false;
    protected $breakValidatorChain = \true;
    protected $viewScript;

    /**
     * @var Form
     */
    protected $form;

    abstract public function render();

    /**
     * @param string $name
     * @param array $options
     */
    public function __construct($name, array $options = [])
    {
        $this->name = $name;

        if (!empty($options)) {
            $this->setOptions($options);
        }
    }

    public function setOptions(array $options)
    {
        foreach ($options as $k => $v) {
            $method = 'set' . ucfirst($k);
            if (\method_exists($this, $method)) {
                $this->$method($v);
            }
        }

        return $this;
    }

    public function bindTo(Form $form)
    {
        $this->form = $form;

        return $this;
    }

    public function setIsArray($flag)
    {
        $this->isArray = (bool) $flag;

        return $this;
    }

    public function setBreakValidatorChain($flag)
    {
        $this->breakValidatorChain = (bool) $flag;

        return $this;
    }

    public function getIsArray()
    {
        return $this->isArray;
    }

    public function setClass($value)
    {
        $this->attributes['class'] = $value;

        return $this;
    }

    public function getClass()
    {
        return isset($this->attributes['class']) ? $this->attributes['class'] : \null;
    }

    public function setShowErrors($value)
    {
        $this->showErrors = (bool) $value;

        return $this;
    }

    public function getShowErrors()
    {
        return $this->showErrors;
    }

    public function setName($value)
    {
        $this->name = $value;

        return $this;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getFullyName($checkIsArray = \true)
    {
        $name = $this->belongsTo !== \null
                ? $this->belongsTo . '[' . $this->name . ']'
                : $this->name;

        if ($checkIsArray && $this->isArray) {
            $name .= '[]';
        }

        return $name;
    }

    public function setTranslate($value)
    {
        $this->translate = (bool) $value;

        return $this;
    }

    public function getTranslate()
    {
        return $this->translate;
    }

    public function setTranslateValue($value)
    {
        $this->translateValue = (bool) $value;

        return $this;
    }

    public function getTranslateValue()
    {
        return $this->translateValue;
    }

    public function setBelongsTo($value)
    {
        $this->belongsTo = $value;

        return $this;
    }

    public function getBelongsTo()
    {
        return $this->belongsTo;
    }

    public function setLabel($value)
    {
        $this->label = $value;

        return $this;
    }

    public function getLabel()
    {
        return $this->translate ? \translate($this->label) : $this->label;
    }

    public function setLabelClass($value)
    {
        $this->labelClass = $value;

        return $this;
    }

    public function getLabelClass()
    {
        return $this->labelClass;
    }

    public function setDescription($value)
    {
        $this->description = $value;

        return $this;
    }

    public function getDescription()
    {
        return $this->translate ? \translate($this->description) : $this->description;
    }

    public function setValue($value)
    {
        $this->value = $value;

        return $this;
    }

    public function getValue()
    {
        return $this->value;
    }

    public function setRequired($flag)
    {
        $this->required = (bool) $flag;

        return $this;
    }

    public function getRequired()
    {
        return $this->isRequired();
    }

    public function isRequired()
    {
        return $this->required;
    }

    public function setAttributes(array $attributes)
    {
        foreach ($attributes as $k => $v) {
            $this->attributes[$k] = $v;
        }

        return $this;
    }

    public function setAttribute($k, $v)
    {
        $this->attributes[$k] = $v;

        return $this;
    }

    public function removeAttribute($k)
    {
        if (isset($this->attributes[$k])) {
            unset($this->attributes[$k]);
        }

        return $this;
    }

    public function getAttribute($key = \null)
    {
        if ($key === \null) {
            return $this->attributes;
        }

        return isset($this->attributes[$key]) ? $this->attributes[$key] : \null;
    }

    public function clearAttributes()
    {
        $this->attributes = [];

        return $this;
    }

    public function setValidators(array $validators)
    {
        $this->clearValidators();

        $this->addValidators($validators);

        return $this;
    }

    public function clearValidators()
    {
        $this->validators = [];

        return $this;
    }

    public function addValidators(array $validators)
    {
        foreach ($validators as $validatorInfo) {

            if (\is_string($validatorInfo) || $validatorInfo instanceof ValidateInterface) {

                $this->addValidator($validatorInfo);

            } elseif (\is_array($validatorInfo)) {

                if (isset($validatorInfo['validator'])) {

                    $validator = $validatorInfo['validator'];

                    $options = [];

                    if (isset($validatorInfo['options'])) {

                        $options = $validatorInfo['options'];
                    }

                    $this->addValidator($validator, $options);

                } else {

                    throw new CoreException('Invalid validator config passed to addValidators()');
                }
            }
        }

        return $this;
    }

    public function addValidator($validator, array $options = [])
    {
        if ($validator instanceof ValidateInterface) {
            $name = \get_class($validator);
        } else if (\is_string($validator)) {
            $name = $validator;
            $validator = [
                'validator' => $validator,
                'options'   => $options,
            ];
        } else {
            throw new CoreException('Invalid validator provided to addValidator; must be string or Micro\Validate\ValidateInterface');
        }

        $this->validators[$name] = $validator;

        return $this;
    }

    public function prependValidator(ValidateInterface $validator)
    {
        $validators = $this->getValidators();

        \array_unshift($validators, $validator);

        $this->setValidators($validators);

        return $this;
    }

    public function getValidators()
    {
        $validators = [];

        foreach ($this->validators as $key => $value) {
            if ($value instanceof ValidateInterface) {
                $validators[$key] = $value;
                continue;
            }
            $validator = $this->loadValidator($value);
            $validators[\get_class($validator)] = $validator;
        }

        return $validators;
    }

    public function removeValidator($name)
    {
        if (!\class_exists($name)) {
            $name = 'Micro\\Validate\\' . \ucfirst($name);
        }

        $validators = $this->getValidators();

        if (isset($validators[$name])) {
            unset($this->validators[$name]);
        }

        return $this;
    }

    protected function loadValidator(array $validator)
    {
        if (!isset($validator['validator'])) {
            throw new CoreException(sprintf('[%s] Validator key does not exists', __METHOD__));
        }

        $origName = $validator['validator'];

        if (\class_exists($origName)) {
            $name = $origName;
        } else {
            $name = 'Micro\\Validate\\' . \ucfirst($origName);
            if (!\class_exists($name)) {
                throw new CoreException('Class [' . $name . '] does not exists');
            }
        }

        $instance = new $name((isset($validator['options']) ? $validator['options'] : []));

        if ($origName != $name) {

            $validatorNames     = \array_keys($this->validators);
            $order              = \array_flip($validatorNames);
            $order[$name]       = $order[$origName];
            $validatorsExchange = [];

            unset($order[$origName]);

            \asort($order);

            foreach ($order as $key => $index) {
                if ($key == $name) {
                    $validatorsExchange[$key] = $instance;
                    continue;
                }
                $validatorsExchange[$key] = $this->validators[$key];
            }

            $this->validators = $validatorsExchange;

        } else {
            $this->validators[$name] = $instance;
        }

        return $instance;
    }

    protected function hasValidator($name)
    {
        $validators = $this->getValidators();

        return isset($validators[$name]);
    }

    public function isValid($value, ?array $context = \null)
    {
        $this->setValue($value);

        $value = $this->getValue();
        $hasNotEmptyValidator = $this->hasValidator(Validate\NotEmpty::class);

        if ((('' === $value) || (\null === $value))
            && $this->required === \false
            && !$hasNotEmptyValidator
        ) {
            return \true;
        }

        if ($this->required === \true && !$hasNotEmptyValidator) {
            $this->prependValidator(new Validate\NotEmpty());
        }

        foreach ($this->getValidators() as $key => $validator) {
            if (!$validator->isValid($value, $context)) {
                foreach ($validator->getMessages() as $message) {
                    $this->addError($message);
                }
                if ($this->breakValidatorChain) {
                    break;
                }
            }
        }

        if ($this->hasErrors()) {
            return \false;
        }

        return \true;
    }

    public function addError($message)
    {
        $this->errors[] = $message;

        if ($this->form instanceof Form) {
            $this->form->markAsError();
        }

        return $this;
    }

    public function getErrors()
    {
        return $this->errors;
    }

    public function hasErrors()
    {
        return !empty($this->errors);
    }

    public function __toString()
    {
        try {
            if ($this->viewScript !== \null) {
                return $this->renderAsView();
            }
            return $this->render();
        } catch (\Throwable $e) {
            \trigger_error($e->getMessage(), E_USER_WARNING);
            return '';
        }
    }

    public function renderAsView($viewScript = \null)
    {
        return render_view($viewScript ?: $this->viewScript, \null, [
            'form'    => $this->form,
            'element' => $this,
        ]);
    }

    public function htmlAttributes()
    {
        $xhtml = '';
        $escaper = new Escaper();

        foreach ($this->attributes as $key => $val) {

            if (\is_array($val)) {
                $val = \implode(' ', $val);
            }

            if (is_int($key)) {
                $xhtml .= \sprintf(' %s', $escaper->escapeHtml($val));
                continue;
            }

            $xhtml .= \sprintf(' %s="%s"', $escaper->escapeHtml($key), $escaper->escapeHtmlAttr($val));
        }

        return $xhtml;
    }

    public function renderLabel()
    {
        if ($this->label) {
            return '<label for="'.$this->name.'" class="' . $this->name . '-element-label' . ($this->labelClass ? ' ' . $this->labelClass : ' element-label') . ($this->required ? ' required' : '') . '">' . $this->getLabel() . ($this->required ? ' <span class="asterisk">*</span>' : '') . '</label>';
        }

        return '';
    }

    public function renderErrors()
    {
        $tmp = '';

        foreach ($this->errors as $error) {
            $tmp .= '<span class="' . $this->name . '-element-error element-error">' . $this->translateError($error) . '</span>';
        }

        return $tmp;
    }

    public function translateError($error)
    {
        if (\is_array($error)) {
            $error[0] = ($this->translate ? \translate($error[0]) : $error[0]);
            $error = \call_user_func_array('sprintf', $error);
        } else {
            $error = ($this->translate ? \translate($error) : $error);
        }

        return $error;
    }

    public function getViewScript()
    {
        return $this->viewScript;
    }

    public function setViewScript($viewScript)
    {
        $this->viewScript = $viewScript;
    }

    public function getClientValidate()
    {
        $clientValidate = '';

        if (!empty($this->validators)) {
            $toClient = [];
            foreach ($this->getValidators() as $validator) {
                if ($validator instanceof ClientValidateInterface) {
                    $toClient[] = $validator->toClient();
                }
            }
            if (!empty($toClient)) {
                $clientValidate .= ' data-validate="' . escape(json_encode($toClient)) . '"';
            }
        }

        return $clientValidate;
    }
}