<?php

namespace UserManagement\Controller\Admin;

use Micro\Exception\Exception as CoreException;
use UserManagement\Model\Users as UsersModel;
use Micro\Form\Form;
use Micro\Model\Entity\EntityInterface;
use Micro\Security\Security;
use UserManagement\Helper\ResourceTreeTrait;
use Micro\Application\Utils;
use Micro\Http\Response\RedirectResponse;
use Micro\Auth\Auth;
use Micro\Application\Controller\Admin;
use Micro\Http\Response;
use UserManagement\Model\Table\UsersGroupsRel;
use Micro\Database\Expr;
use Sonata\GoogleAuthenticator\GoogleAuthenticator;

class Users extends Admin
{
    use ResourceTreeTrait;

    protected $model = UsersModel::class;

    protected $gridPath;

    public function indexAction()
    {
        if (($response = parent::indexAction()) instanceof Response) {
            return $response;
        }

        $form = new Form(module_path('UserManagement') . '/Resources/forms/admin/users-filters.php');
        $form->populate($this->view->filters);

        $this->attachGroupIds();

        if (!config('auth.force_logout', false)) {
            $this->view->grid->removeColumn('killSession');
        }

        return $this->view->assign([
            'form' => $form
        ]);
    }

    protected function attachGroupIds()
    {
        $userIds = [];
        foreach ($this->view->grid->getPaginator() as $item) {
            $userIds[] = $item->getId();
        }

        if (!empty($userIds)) {
            $usersGroupsRel = [];
            foreach ((new UsersGroupsRel())->fetchAll(['userId IN (?)' => $userIds]) as $r) {
                if (!isset($usersGroupsRel[$r['userId']])) {
                    $usersGroupsRel[$r['userId']] = [];
                }
                $usersGroupsRel[$r['userId']][] = $r['groupId'];
            }
            foreach ($this->view->grid->getPaginator() as $item) {
                if (isset($usersGroupsRel[$item->getId()])) {
                    $item->setGroupId($usersGroupsRel[$item->getId()][0]);
                }
            }
        }
    }

    protected function modifyModel(array $filters)
    {
        $model = $this->getModel();

        $model->resetSelect();

        $select = $model->getSelect();

        try {
            $domain = $this->container->get('domain');
            if ($domain) {
                $select->where('domainId = ?', $domain->getId());
            }
        } catch (\Exception $e) {

        }

        if (isset($filters['email']) && !empty($filters['email'])) {
            $select->where('Users.email LIKE ?', '%' . $filters['email'] . '%');
        }

        if (isset($filters['names']) && !empty($filters['names'])) {
            $db = $this->container->get('db');
            $select->where(new Expr(sprintf('firstName LIKE %1$s OR middleName LIKE %1$s OR lastName LIKE %1$s', $db->quote('%' . $filters['names'] . '%'))));
        }
    }

    public function editAction()
    {
        $model = $this->getModel();

        $entity = $model->find((int) $this->request->getParam('id', 0));

        if ($entity === null) {
            throw new CoreException(\sprintf('Записът не е намерен'), 404);
        }

        return $this->addAction($entity);
    }

    protected function prepareForm(Form $form, EntityInterface $entity)
    {
        if ($entity->getId()) {
            $form->password->setRequired(0);
            $form->repassword->setRequired(0);
            $form->groupId->setValue(array_keys($entity->getGroups()));
            if ($entity->getCustomRights()) {
                $form->getElement('customRights')->setValue(\json_decode($entity->getCustomRights(), \true));
            }
        }

        $this->view->assign([
            'tree' => $this->getResourceTree($entity)
        ]);
    }

    protected function modifyData(array &$data)
    {
        if ($data['password']) {
            $data['password'] = Security::hash(sha1($data['password']));
        } else {
            unset($data['password']);
        }
    }

    protected function modifyEntity(EntityInterface $entity, array $data)
    {
        $this->bindEntityToRights($entity);

        if (!$entity->getId()) {
            try {
                $domain = $this->container->get('domain');
                if ($domain) {
                    $entity->setDomainId($domain->getId());
                }
                if (config('auth.2fa')) {
                    $entity->setAuth2fa('email');
                }
            } catch (\Exception $e) {

            }
        }
    }

    public function profileAction()
    {
        $identity = Auth::identity();
        $model = $this->getModel();

        if ($identity === \null || ($item = $model->find((int) $identity->getId())) === \null) {
            throw new \Exception('Невалиден потребител');
        }

        $form = new Form(include module_path('UserManagement') . '/Resources/forms/admin/profile.php');
        $form->populate($item->toArray());

        $post = $item->toArray();

        if ($this->request->isPost()) {

            $post = $this->request->getPost();
            $post = Utils::arrayMapRecursive('trim', $post);

            $form->isValid($post);

            $el = $form->getElement('email');
            if ($el->hasErrors() === false) {
                $db = $model->getTable()->getAdapter();
                $userId = $db->fetchOne('SELECT id FROM Users WHERE id <> ' . $identity->getId() . ' AND domainId = ' . DOMAIN . ' AND username = ' . $db->quote($el->getValue()));
                if ($userId) {
                    $el->addError('Потребителят съществува в базата данни');
                }
            }

            if (!$form->hasErrors()) {

                $post = Utils::arrayMapRecursive('trim', $post, \true);

                $item->setEmail($post['email']);

                $auth2fa = $post['auth2fa'] ?? null;

                $item->setAuth2fa($auth2fa);

                if ($auth2fa == 'google' && !$item->getAuth2faSecret()) {
                    $item->setAuth2faSecret((new GoogleAuthenticator())->generateSecret());
                }

                if ($post['password']) {
                    $item->setPassword(Security::hash(sha1($post['password'])));
                }

                $redirectResponse = new RedirectResponse(route());

                try {
                    $model->save($item);
                    $model->storeInSession($item, ['step' => config('auth.2fa') && $auth2fa ? 2 : 1]);
                    return $redirectResponse->withFlash('Информацията е записана');
                } catch (\Exception $e) {
                    return $redirectResponse->withFlash((env('development') ? $e->getMessage() : 'Възникна грешка. Опитайте по-късно'), 'danger');
                }
            }
        }

        return [
            'form' => $form,
            'item' => $item,
            'post' => $post,
        ];
    }

    protected function getGridPath()
    {
        return $this->gridPath;
    }

    public function killSessionAction()
    {
        $id = (int) $this->request->getParam('id');

        (new UsersModel())->getTable()->update(['forceLogout' => 1], ['id = ?' => $id]);

        return (new RedirectResponse(route(\null, ['action' => 'index', 'id' => null, 'ids' => null, static::CURRENT_LANG_KEY => null])))->withFlash('Сесията на потребителята беше приключена.');
    }
}