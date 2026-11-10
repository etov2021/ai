<?php

namespace Translations\Controller\Admin;

use Micro\Form\Form;
use Micro\Model\Entity\EntityInterface;
use Micro\Form\Element\Text;
use Translations\Model\Table\TranslationsLang;
use Translations\Model\Translations as ModelTranslations;
use Micro\Application\Controller\Admin;
use Micro\Http\Response;

class Index extends Admin
{
    protected $model = ModelTranslations::class;

    protected $ipp = 50;

    public function indexAction()
    {
        if (($response = parent::indexAction()) instanceof Response) {
            return $response;
        }

        $form = new Form(module_path('Translations') . '/Resources/forms/admin/index-filters.php');

        $form->populate($this->view->filters);

        return $this->view->assign([
            'form' => $form
        ]);
    }

    protected function modifyModel(array $filters)
    {
        $model = $this->getModel();

        $model->resetWhere();

        if (isset($filters['alias'])) {
            $model->addWhere('alias', '%' . $filters['alias'] . '%');
        }

        if (isset($filters['name']) && isset($filters['languageId'])) {

            $model->useLanguageById($filters['languageId']);

            $model->addWhere('languageId',  $filters['languageId']);

            $model->addWhere('name', '%' . $filters['name'] . '%');
        }
    }

    protected function prepareForm(Form $form, EntityInterface $entity)
    {
        if (($languages = \app('languages', \true)) !== \null) {

            $translations = [];

            if ($entity->getId()) {
                $translationsLangTable = new TranslationsLang();
                $translations = $translationsLangTable->getLanguagePairs($entity->getId());
            }

            foreach ($languages as $language) {
                $el = new Text('language_' . $language->getId());
                $el->setClass('form-control');
                $el->setLabel($language->getName());
                $el->setLabelClass('control-label');
                $el->setBelongsTo('values');
                if (isset($translations[$language->getId()])) {
                    $el->setValue($translations[$language->getId()]);
                }
                $el->language = $language;
                $form->addElement($el);
            }
        }
    }

    protected function modifyEntity(EntityInterface $entity, array $data)
    {
        $values = $entity->getValues();

        $languages = [];

        if (!empty($values)) {
            foreach ($values as $k => $v) {
                $languages[\str_replace('language_', '', $k)] = trim($v);
            }
        }

        $entity->setValues($languages);
        $entity->setAlias(\trim($entity->getAlias()));
    }
}