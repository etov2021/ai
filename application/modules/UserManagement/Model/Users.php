<?php

namespace UserManagement\Model;

use Micro\Model\DatabaseAbstract;
use Micro\Model\Entity\EntityInterface;
use UserManagement\Model\Table\UsersGroupsRel;
use Micro\Auth\Identity;
use Micro\Auth\Auth;
use UserManagement\Model\Entity\User;

class Users extends DatabaseAbstract
{
    protected $entity = Entity\User::class;

    protected $table = Table\Users::class;

    public function save(EntityInterface $entity)
    {
        try {

            $this->beginTransaction();

            $result = parent::save($entity);

            // from form
            if (($groupIds = $entity->getGroupId()) !== \null) {
                $rel = new UsersGroupsRel();
                $rel->delete(['userId = ?' => $entity->getId()]);
                foreach ((array) $groupIds as $groupId) {
                    $row = $rel->createRow([
                        'userId' => $entity->getId(),
                        'groupId' => $groupId
                    ]);
                    $row->save();
                }
            }

            $this->commit();

        } catch (\Exception $e) {
            $this->rollback();
            throw $e;
        }

        return $result;
    }

    public function login($username, $password)
    {
        $username = trim($username);
        $password = trim($password);

        if (empty($username) || empty($password)) {
            return null;
        }

        $this->resetSelect();

        $this->addWhere('username', $username);
        $this->addWhere('active', 1);

        $user = $this->getItem();

        if ($user instanceof Entity\User && $user->verifyPassword($password)) {
            return $user;
        }

        return null;
    }

    public function storeInSession(Identity $user, array $data = [])
    {
        $user->getGroups();

        Auth::getInstance()->setIdentity(['user' => $user] + $data);
    }

    public function forceLogout(Identity $identity)
    {
        try {
            $this->beginTransaction();
            $this->getTable()->update(['forceLogout' => 0, 'lastLoginTime' => null, 'refreshTime' => null], ['id = ?' => $identity->getId()]);
            if (Auth::getInstance()->hasIdentity()) {
                Auth::getInstance()->clearIdentity();
            }
            $this->commit();
        } catch (\Throwable $e) {
            $this->rollback();
        }
        $this->rollback();
    }

    public function fetchStatusUsers()
    {
        $select = $this->getSelect();

        $select->joinLeft('UsersGroupsRel', 'UsersGroupsRel.userId = Users.id', [])
                ->joinLeft('UsersGroups', 'UsersGroupsRel.groupId = UsersGroups.id', [])
                 ->where('alias = ?', Groups::STATUS_GROUP_ALIAS);

        return $this->getTable()->fetchAll($select);
    }

    public function fetchUsersByGroups($groups)
    {
        if (!is_array($groups)) {
            $groups = [$groups];
        }

        $groups = array_filter($groups);

        if (empty($groups)) {
            return [];
        }

        $select = $this->getSelect();

        $select->joinLeft('UsersGroupsRel', 'UsersGroupsRel.userId = Users.id', [])
               ->joinLeft('UsersGroups', 'UsersGroupsRel.groupId = UsersGroups.id', [])
               ->where('alias IN (?)', $groups);

        return $this->getTable()->fetchAll($select);
    }

    public function fetchUsersEmailsByGroups($groups)
    {
        $users = $this->fetchUsersByGroups($groups);

        foreach ($users as $user) {
            $emails[] = $user['email'];
        }

        $emails = array_unique($emails);

        return $emails;
    }
}