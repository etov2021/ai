<?php

use Micro\Database\Table\Select;

$routesPairs = array();

foreach (app('router')->getRoutes() as $route) {
    $routesPairs[$route->getName()] = translate($route->getDescription());
}

asort($routesPairs);

return array(
    'elements' => array(
        'name' => array(
            'type' => 'text',
            'options' => array(
                'label' => 'Име',
                'labelClass' => 'form-label',
                'required' => 1,
                'class' => 'form-control',
                'attributes' => [
                    'data-lang-icon',
                ],
            )
        ),
        'alias' => [
            'type' => 'text',
            'options' => [
                'required' => 0,
                'label' => 'Служебно име',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'validators' => [
                    'exists' => [
                        'validator' => 'FieldExists',
                        'options'   => [
                            'table' => Navigation\Model\Table\Items::class,
                            'field' => 'MenuItems.alias',
                            'callback' => function (Select $select) {
                                $domain = app('domain', true);
                                if ($domain) {
                                    $select->joinInner('Menu', 'Menu.id = MenuItems.menuId AND Menu.domainId = ' . $domain->getId(), []);
                                }
                            }
                        ],
                    ],
                ]
            ],
        ],
        'description' => array(
            'type' => 'textarea',
            'options' => array(
                'label' => 'Описание',
                'labelClass' => 'form-label',
                'required' => 0,
                'class' => 'form-control',
                'attributes' => [
                    'data-lang-icon',
                    'rows' => 5,
                ],
            )
        ),
        'class' => array(
            'type' => 'text',
            'options' => array(
                'required' => 0,
                'label' => 'Клас',
                'labelClass' => 'form-label',
                'class' => 'form-control'
            )
        ),
        'active' => array(
            'type' => 'checkbox',
            'options' => array(
                'label' => 'Активност',
                'labelClass' => 'form-label',
            )
        ),
        'visible' => array(
            'type' => 'checkbox',
            'options' => array(
                'label' => 'Видимост',
                'labelClass' => 'form-label',
            )
        ),
        'url' => array(
            'type' => 'text',
            'options' => array(
                'label' => 'Връзка',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'attributes' => [
                    'data-lang-icon',
                ],
            )
        ),
        'route' => array(
            'type' => 'select',
            'options' => array(
                'label' => 'Път',
                'labelClass' => 'form-label',
                'emptyOption' => 'Избери',
                'multiOptions' => $routesPairs,
                'translate' => true,
                'class' => 'form-control select2',
                'attributes' => array(
                    'id' => 'route',
                    'data-lang-icon',
                )
            )
        ),
        'reset' => array(
            'type' => 'checkbox',
            'options' => array(
                'label' => 'Нулиране на параметрите',
                'labelClass' => 'form-label',
            )
        ),
        'targetBlank' => [
            'type' => 'checkbox',
            'options' => [
                'label' => 'Отваряне в нова страница',
                'labelClass' => 'form-label',
            ],
        ],
        'parentId' => array(
            'type' => 'select',
            'options' => array(
                'label' => 'Родител',
                'labelClass' => 'form-label',
                'class' => 'form-control select2',
                'emptyOption' => 'Без родител',
            )
        ),
        'handlerId' => array(
            'type' => 'select',
            'options' => array(
                'isArray' => 1,
                'label' => 'Предефинирани елементи',
                'labelClass' => 'form-label',
                'class' => 'form-control',
                'multiOptions' => Navigation\Helper\CustomItems::fetch(),
                'attributes' => [
                    'data-lang-icon',
                    'style' => 'min-height: 200px',
                ],
            )
        ),
        'handlerPosition' => [
            'type' => 'checkbox',
            'options' => [
                'label' => 'Добави предефинираните елементи след дефинираните елементи',
                'labelClass' => 'form-label',
                'attributes' => [
                    'data-lang-icon',
                    'style' => 'vertical-align: top;',
                ],
            ],
        ],
        'btnSave'  => ['type' => 'submit', 'options' => ['value' => 'Запазване', 'class' => 'btn btn-primary']],
        'btnApply' => ['type' => 'submit', 'options' => ['value' => 'Прилагане', 'class' => 'btn btn-success']],
        'btnBack'  => ['type' => 'submit', 'options' => ['value' => 'Назад', 'class' => 'btn btn-default']],
    )
);