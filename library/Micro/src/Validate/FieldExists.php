<?php

namespace Micro\Validate;

use Micro\Database\Table\TableAbstract;
use Micro\Model\ModelInterface;
use Micro\Database\Expr;
use Micro\Model\TranslationDatabaseAbstract;

class FieldExists extends ValidateAbstract
{
    protected $templates = [
        self::ERROR => 'Полето се използва'
    ];

    protected $model;
    protected $table;
    protected $field;
    protected $key;
    protected $callback;

    public function setModel($model)
    {
        $this->model = $model;
    }

    public function setTable($table)
    {
        $this->table = $table;
    }

    public function setField($field)
    {
        $this->field = $field;
    }

    public function setKey($key)
    {
        $this->key = $key;
    }

    public function setCallback($callback)
    {
        $this->callback = $callback;
    }

    public function isValid($value, ?array $context = \null)
    {
        if (empty($value)) {
            return \true;
        }

        if ($this->model !== \null && \class_exists($this->model)) {

            $model = $this->model;

            $instance = new $model;

            if ($instance instanceof ModelInterface) {

                $id = \null;

                $primaryKey = $this->key;

                if ($primaryKey === \null) {
                    $primaryKey = $instance->getIdentifier();
                }

                if (isset($context[$primaryKey])) {
                    $id = $context[$primaryKey];
                }

                try {
                    if ($instance instanceof TranslationDatabaseAbstract) {
                        $instance->useCurrentLanguage();
                    }
                    $instance->addWhere($this->field, $value);
                    if (!empty($id)) {
                        $instance->addWhere(new Expr($primaryKey . ' <> ' . (int) $id));
                    }
                    if ($instance->getItem() !== \null) {
                        $this->messages[] = $this->templates[self::ERROR];
                        return \false;
                    }
                } catch (\Throwable $e) {
                    $this->messages[] = $e->getMessage();
                    return \false;
                }
            }
        }

        if ($this->table !== \null && \class_exists($this->table)) {

            $table = $this->table;

            $instance = new $table;

            if ($instance instanceof TableAbstract) {

                $id = \null;

                $primaryKey = $this->key;

                if ($primaryKey === \null) {
                    $primaryKey = \current($instance->info('primary'));
                }

                if (isset($context[$primaryKey])) {
                    $id = $context[$primaryKey];
                }

                try {
                    if ($instance->fieldExists($this->field, $value, $id, $this->callback, $context)) {
                        $this->messages[] = $this->templates[self::ERROR];
                        return \false;
                    }
                } catch (\Throwable $e) {
                    $this->messages[] = $e->getMessage();
                    return \false;
                }
            }
        }

        return \true;
    }
}
