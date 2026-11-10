<?php

namespace Micro\Form;


use Micro\Exception\Exception as CoreException;

class Form
{
    /**
     * @var array
     */
    protected $elements = [];

    /**
     * @var boolean
     */
    protected $errorsExist = \false;

    /**
     * Whether or not form elements are members of an array
     * @var bool
     */
    protected $isArray = \false;

    /**
     * Array to which elements belong (if any)
     * @var string
     */
    protected $elementsBelongTo;

    /**
     * Sub forms
     * @var array
     */
    protected $subForms = [];

    /**
     * @var string
     */
    protected $name;

    /**
     * Form constructor
     * @param array|string $options
     * @param array $defaultValues
     * @throws \Exception
     */
    public function __construct($options = [], array $defaultValues = [])
    {
        if (\is_string($options) && \file_exists($options)) {
            $options = include $options;
            $options = \is_array($options) ? $options : [];
        }

        if (!\is_array($options)) {
            throw new CoreException('Invalid data in ' . __METHOD__ . ' (' . (\is_string($options) ? $options : \json_encode($options)) . ')');
        }

        if (!empty($options)) {
            $this->setOptions($options);
        }

        if (!empty($defaultValues)) {
            $this->populate($defaultValues);
        }
    }

    /**
     * @param array $options
     * @return \Micro\Form\Form
     */
    public function setOptions(array $options)
    {
        if (isset($options['name'])) {
            $this->setName($options['name']);
        }

        if (isset($options['elements'])) {
            $this->setElements((array) $options['elements']);
        }

        if (isset($options['sub_forms'])) {
            $this->addSubForms((array) $options['sub_forms']);
        }

        return $this;
    }

    /**
     * @param array $elements
     * @return \Micro\Form\Form
     */
    public function setElements(array $elements)
    {
        foreach ($elements as $name => $config) {
            $this->addElement($name, $config);
        }

        return $this;
    }

    /**
     * @param string $name
     * @param array $config
     * @throws \Exception
     * @return Form
     */
    public function addElement($name, $config = \null)
    {
        $instance = \null;

        if ($name instanceof Element) {
            $instance = $name;
        } else if (\is_string($name) && \is_array($config) && isset($config['type'])) {
            $instance = $this->createElement($name, $config['type'], (isset($config['options']) ? $config['options'] : []));
        } else if (\is_string($name) && \is_string($config)) {
            $instance = $this->createElement($name, $config, []);
        }

        if (!$instance instanceof Element) {
            throw new CoreException('The element is not instance of Micro\\Form\\Element');
        }

        $instance->bindTo($this);

        $this->elements[$instance->getName()] = $instance;
        $this->setElementsBelongToHelper($instance->getName());

        return $this;
    }

    /**
     * @param string $name
     * @param string $element
     * @param array $options
     * @throws \Exception
     * @return \Micro\Form\Element
     */
    protected function createElement($name, $element, array $options)
    {
        $instance = 'Micro\Form\Element\\' . \ucfirst($element);

        if (\class_exists($instance, \true)) {
            return new $instance($name, $options);
        }

        $instance = $element;

        if (\class_exists($instance, \true)) {
            return new $instance($name, $options);
        }

        throw new CoreException(\sprintf('[%s] Class [%s] does not exists!', __METHOD__, $instance));
    }

    /**
     * @param $name
     * @return Element|null
     */
    public function getElement($name)
    {
        return isset($this->elements[$name]) ? $this->elements[$name] : \null;
    }

    /**
     * return array of Element
     */
    public function getElements()
    {
        return $this->elements;
    }

    /**
     * @param $name
     * @return \Micro\Form\Form
     */
    public function removeElement($name)
    {
        if (\is_array($name)) {
            foreach ($name as $singleName) {
                $this->removeElement($singleName);
            }
            return $this;
        }

        if (isset($this->elements[$name])) {
            unset($this->elements[$name]);
        }

        return $this;
    }

    /**
     * Reset elements values
     * @return Form
     */
    public function reset()
    {
        foreach ($this->elements as $element) {
            $element->setValue(\null);
        }

        foreach ($this->getSubForms() as $subForm) {
            $subForm->reset();
        }

        return $this;
    }

    /**
     * @param array $data
     * @return boolean
     */
    public function isValid(array $data)
    {
        $valid = \true;

        $eBelongTo = \null;
        $context = $data;

        if ($this->isArray()) {
            $eBelongTo = $this->getElementsBelongTo();
            $data = $this->dissolveArrayValue($data, $eBelongTo);
        }

        foreach ($this->elements as $key => $element) {
            $check = $data;
            if (($belongsTo = $element->getBelongsTo()) !== $eBelongTo) {
                $check = $this->dissolveArrayValue($data, $belongsTo);
            }
            if (!isset($check[$key])) {
                $valid = $element->isValid(\null, $context) && $valid;
            } else {
                $valid = $element->isValid($check[$key], $context) && $valid;
                $data = $this->dissolveArrayUnsetKey($data, $belongsTo, $key);
            }
        }

        foreach ($this->getSubForms() as $key => $form) {
            if (isset($data[$key]) && !$form->isArray()) {
                $valid = $form->isValid($data[$key]) && $valid;
            } else {
                $valid = $form->isValid($data) && $valid;
            }
        }

        $this->errorsExist = !$valid;

        return $valid;
    }

    /**
     * Get error codes for all elements failing validation
     *
     * @param  string $name
     * @param  bool   $suppressArrayNotation
     * @return array
     */
    public function getErrors($name = \null, $suppressArrayNotation = \false)
    {
        if (\null !== $name) {
            if (isset($this->elements[$name])) {
                return $this->getElement($name)->getErrors();
            } else if (isset($this->subForms[$name])) {
                return $this->getSubForm($name)->getErrors(null, true);
            }
            return [];
        }

        $errors = [];

        foreach ($this->elements as $key => $element) {
            $errors[$key] = $element->getErrors();
        }

        foreach ($this->getSubForms() as $key => $subForm) {
            $merge = [];
            if (!$subForm->isArray()) {
                $merge[$key] = $subForm->getErrors();
            } else {
                $merge = $this->attachToArray($subForm->getErrors(null, true), $subForm->getElementsBelongTo());
            }
            $errors = array_replace_recursive($errors, $merge);
        }

        if (!$suppressArrayNotation && $this->isArray()) {
            $errors = $this->attachToArray($errors, $this->getElementsBelongTo());
        }

        return $errors;
    }

    /**
     * @return boolean
     */
    public function hasErrors()
    {
        $errors = $this->errorsExist;

        if (!$errors) {
            foreach ($this->getElements() as $element) {
                if ($element->hasErrors()) {
                    $errors = true;
                    break;
                }
            }
            foreach ($this->getSubForms() as $subForm) {
                if ($subForm->hasErrors()) {
                    $errors = true;
                    break;
                }
            }
        }

        return $errors;
    }

    /**
     * @param array $defaults
     */
    public function populate(array $defaults)
    {
        $eBelongTo = \null;

        if ($this->isArray()) {
            $eBelongTo = $this->getElementsBelongTo();
            $defaults = $this->dissolveArrayValue($defaults, $eBelongTo);
        }

        foreach ($this->elements as $name => $element) {
            $check = $defaults;
            if (($belongsTo = $element->getBelongsTo()) !== $eBelongTo) {
                $check = $this->dissolveArrayValue($defaults, $belongsTo);
            }
            if (\is_array($check) && \array_key_exists($name, $check)) {
                $element->setValue($check[$name]);
                $defaults = $this->dissolveArrayUnsetKey($defaults, $belongsTo, $name);
            }
        }

        foreach ($this->getSubForms() as $name => $form) {
            if (!$form->isArray() && array_key_exists($name, $defaults)) {
                $form->populate($defaults[$name]);
            } else {
                $form->populate($defaults);
            }
        }

        return $this;

    }

    /**
     * @return array
     */
    public function getValues($suppressArrayNotation = false)
    {
        $eBelongTo = \null;
        $values = [];

        if ($this->isArray()) {
            $eBelongTo = $this->getElementsBelongTo();
        }

        foreach ($this->getElements() as $key => $element) {
            $merge = [];
            if (($belongsTo = $element->getBelongsTo()) !== $eBelongTo) {
                if ('' !== (string) $belongsTo) {
                    $key = $belongsTo . '[' . $key . ']';
                }
            }
            $merge = $this->attachToArray($element->getValue(), $key);
            $values = array_replace_recursive($values, $merge);
        }

        foreach ($this->getSubForms() as $key => $subForm) {
            $merge = [];
            if (!$subForm->isArray()) {
                $merge[$key] = $subForm->getValues();
            } else {
                $merge = $this->attachToArray($subForm->getValues(true), $subForm->getElementsBelongTo());
            }
            $values = array_replace_recursive($values, $merge);
        }

        if (!$suppressArrayNotation && $this->isArray()) {
            $values = $this->attachToArray($values, $this->getElementsBelongTo());
        }

        return $values;
    }

    /**
     * Overloading: access to elements and form groups
     *
     * @param  string $name
     * @return Element|Form|null
     */
    public function __get($key)
    {
        if (isset($this->elements[$key])) {
            return $this->elements[$key];
        } elseif (isset($this->subForms[$key])) {
            return $this->subForms[$key];
        }

        return \null;
    }

    /**
     * Overloading: access to elements and form groups
     *
     * @param string $name
     * @param Element|Form $value
     * @return void
     * @throws Exception for invalid $value
     */
    public function __set($name, $value)
    {
        if ($value instanceof Element) {
            $this->addElement($value, $name);
            return;
        } elseif ($value instanceof Form) {
            $this->addSubForm($value, $name);
            return;
        }

        if (is_object($value)) {
            $type = get_class($value);
        } else {
            $type = gettype($value);
        }

        throw new CoreException('Only form elements may be overloaded; variable of type "' . $type . '" provided');
    }

    /**
     * Overloading: access to elements and form groups
     *
     * @param  string $name
     * @return boolean
     */
    public function __isset($name)
    {
        if (isset($this->elements[$name]) || isset($this->subForms[$name])) {
            return true;
        }

        return false;
    }

    /**
     * Overloading: access to elements and form groups
     *
     * @param  string $name
     * @return void
     */
    public function __unset($name)
    {
        if (isset($this->elements[$name])) {
            unset($this->elements[$name]);
        } elseif (isset($this->subForms[$name])) {
            unset($this->subForms[$name]);
        }
    }

    /**
     * @return \Micro\Form\Form
     */
    public function markAsError()
    {
        $this->errorsExist = \true;

        return $this;
    }

    /**
     * Set name of form
     *
     * @param  string $name
     * @return Form
     */
    public function setName($name)
    {
        $name = $this->filterName($name);
        if ('' === (string) $name) {
            throw new CoreException('Invalid name provided; must contain only valid variable characters and be non-empty');
        }

        $this->name = $name;
    }

    /**
     * Get name of form
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set flag indicating elements belong to array
     *
     * @param  bool $flag Value of flag
     * @return Form
     */
    public function setIsArray($flag)
    {
        $this->isArray = (bool) $flag;
        return $this;
    }

    /**
     * Get flag indicating if elements belong to an array
     *
     * @return bool
     */
    public function isArray()
    {
        return $this->isArray;
    }

    /**
    * Add a form group/subform
    *
    * @param  Form $form
    * @param  string $name
    * @param  int $order
    * @return Form
    */
    public function addSubForm(Form $form, $name)
    {
        $name = (string) $name;

        $form->setName($name);

        $this->subForms[$name] = $form;

        $form->setElementsBelongToHelper();

        return $this;
    }

    /**
     * Add multiple form subForms/subforms at once
     *
     * @param  array $subForms
     * @return Form
     */
    public function addSubForms(array $subForms)
    {
        foreach ($subForms as $key => $spec) {
            $name = (string) $key;
            if ($spec instanceof Form) {
                $this->addSubForm($spec, $name);
                continue;
            }

            if (is_array($spec)) {
                $argc  = count($spec);
                switch ($argc) {
                    case 0:
                        break;
                    case (1 <= $argc):
                        $subForm = array_shift($spec);
                        if (!$subForm instanceof Form) {
                            $subForm = new SubForm($subForm);
                        }
                    case (2 <= $argc):
                        $name  = array_shift($spec);
                    default:
                        $this->addSubForm($subForm, $name);
                }
            }
        }

        return $this;
    }

    /**
     * Set multiple form subForms/subforms (overwrites)
     *
     * @param  array $subForms
     * @return Form
     */
    public function setSubForms(array $subForms)
    {
        $this->clearSubForms();
        return $this->addSubForms($subForms);
    }

    /**
     * Retrieve a form subForm/subform
     *
     * @param  string $name
     * @return Form|null
     */
    public function getSubForm($name)
    {
        $name = (string) $name;
        if (isset($this->subForms[$name])) {
            return $this->subForms[$name];
        }
        return null;
    }

    /**
     * Retrieve all form subForms/subforms
     *
     * @return array
     */
    public function getSubForms()
    {
        return $this->subForms;
    }

    /**
     * Remove form subForm/subform
     *
     * @param  string $name
     * @return boolean
     */
    public function removeSubForm($name)
    {
        $name = (string) $name;
        if (array_key_exists($name, $this->subForms)) {
            unset($this->subForms[$name]);
            return true;
        }

        return false;
    }

    /**
     * Remove all form subForms/subforms
     *
     * @return Form
     */
    public function clearSubForms()
    {
        $this->subForms = [];
        return $this;
    }

    /**
     * Set name of array elements belong to
     *
     * @param  string $array
     * @return Form
     */
    public function setElementsBelongTo($array)
    {
        $origName = $this->getElementsBelongTo();

        $name = $this->filterName($array, true);

        if ('' === $name) {
            $name = null;
        }

        $this->elementsBelongTo = $name;

        if (null === $name) {
            $this->setIsArray(false);
            if (null !== $origName) {
                $this->setElementsBelongToHelper();
            }
        } else {
            $this->setIsArray(true);
            $this->setElementsBelongToHelper();
        }

        return $this;
    }

    /**
     * Get name of array elements belong to
     *
     * @return string|null
     */
    public function getElementsBelongTo()
    {
        if ((null === $this->elementsBelongTo) && $this->isArray()) {
            $name = $this->getName();
            if ('' !== (string)$name) {
                return $name;
            }
        }
        return $this->elementsBelongTo;
    }

    // helpers

    /**
     * Filter a name to only allow valid variable characters
     *
     * @param  string $value
     * @param  bool $allowBrackets
     * @return string
     */
    protected function filterName($value, $allowBrackets = false)
    {
        $charset = '^a-zA-Z0-9_\x7f-\xff';

        if ($allowBrackets) {
            $charset .= '\[\]';
        }

        return preg_replace('/[' . $charset . ']/', '', (string) $value);
    }

    /**
     * Set array to which elements belong
     *
     * @param  string $name Element name
     * @return void
     */
    protected function setElementsBelongToHelper($name = null)
    {
        $array = $this->getElementsBelongTo();

        if (null === $array) {
            return;
        }

        if (null === $name) {
            /** @var Element $element */
            foreach ($this->getElements() as $element) {
                $element->setBelongsTo($array);
            }
        } else {
            if (null !== ($element = $this->getElement($name))) {
                $element->setBelongsTo($array);
            }
        }
    }

    protected function dissolveArrayValue($value, $arrayPath)
    {
        // As long as we have more levels
        while (($arrayPos = \strpos($arrayPath, '[')) !== \false) {
            // Get the next key in the path
            $arrayKey = \trim(\substr($arrayPath, 0, $arrayPos), ']');
            // Set the potentially final value or the next search point in the array
            if (isset($value[$arrayKey])) {
                $value = $value[$arrayKey];
            }
            // Set the next search point in the path
            $arrayPath = \trim(\substr($arrayPath, $arrayPos + 1), ']');
        }

        if (isset($value[$arrayPath])) {
            $value = $value[$arrayPath];
        }

        return $value;
    }

    protected function dissolveArrayUnsetKey($array, $arrayPath, $key)
    {
        $unset =& $array;
        $path  = \trim(\strtr((string) $arrayPath, ['[' => '/', ']' => '']), '/');
        $segs  = ('' !== $path) ? explode('/', $path) : [];

        foreach ($segs as $seg) {
            if (!\array_key_exists($seg, (array) $unset)) {
                return $array;
            }
            $unset =& $unset[$seg];
        }

        if (\array_key_exists($key, (array) $unset)) {
            unset($unset[$key]);
        }

        return $array;
    }

    protected function attachToArray($value, $arrayPath)
    {
        // As long as we have more levels
        while (($arrayPos = \strrpos($arrayPath, '[')) !== \false) {
            // Get the next key in the path
            $arrayKey = \trim(\substr($arrayPath, $arrayPos + 1), ']');
            // Attach
            $value = [$arrayKey => $value];
            // Set the next search point in the path
            $arrayPath = \trim(\substr($arrayPath, 0, $arrayPos), ']');
        }

        $value = [$arrayPath => $value];

        return $value;
    }

    public function mergeBelongsTo($baseBelongsTo, $belongsTo)
    {
        $endOfArrayName = strpos($belongsTo, '[');

        if ($endOfArrayName === false) {
            return $baseBelongsTo . '[' . $belongsTo . ']';
        }

        $arrayName = substr($belongsTo, 0, $endOfArrayName);

        return $baseBelongsTo . '[' . $arrayName . ']' . substr($belongsTo, $endOfArrayName);
    }
}