<?php

namespace UserManagement\Model\Entity;

use Micro\Auth\Identity;
use Micro\Acl\RoleInterface;
use Micro\Model\Entity\EntityAbstract;
use UserManagement\Model\Table\UsersGroupsRel;
use Pages\Model\Entity\PageInterface;
use Micro\Model\Entity\TargetTrait;
use Domains\Model\Entity\DomainInterface;
use Domains\Model\Entity\DomainTrait;
use Micro\Security\Security;
use Translations\Model\Entity\LanguageInterface;
use Translations\Model\Entity\LanguageTrait;
use Files\Entity\FileTrait;
use Files\Entity\FileInterface;

class User extends EntityAbstract implements Identity, RoleInterface, PageInterface, DomainInterface, LanguageInterface, FileInterface
{
    use RightsTrait, TargetTrait, DomainTrait, LanguageTrait, FileTrait;

    protected $id;
    protected $username;
    protected $password;
    protected $email;
    protected $active = 0;
    protected $groups;
    protected $currentGroup;
    protected $roleId;

    protected $firstName;
    protected $middleName;
    protected $lastName;
    protected $phone;
    protected $address;
    protected $image;

    protected $forceLogout;
    protected $lastLoginTime;
    protected $refreshTime;

    protected $auth2fa;
    protected $auth2faSecret;

    /**
     * Използва се само от формата да се попълнят и после се запазват в релацията
     * @var array|null
     */
    protected $groupId;

    public function getId()
    {
        return $this->id;
    }

    public function setId($value)
    {
        $this->id = $value;
    }

    public function getUsername()
    {
        return $this->username;
    }

    public function setUsername($value)
    {
        $this->username = $value;
    }

    public function getPassword()
    {
        return $this->password;
    }

    public function setPassword($value)
    {
        $this->password = $value;
    }

    public function getEmail()
    {
        return $this->email;
    }

    public function setEmail($value)
    {
        $this->email = $value;
    }

    public function getActive()
    {
        return $this->active;
    }

    public function setActive($value)
    {
        $this->active = $value ? 1 : 0;
    }

    public function isActive()
    {
        return $this->active;
    }

    public function getFirstName()
    {
        return $this->firstName;
    }

    public function setFirstName ($firstName)
    {
        $this->firstName = $firstName;
    }

    public function getMiddleName()
    {
        return $this->middleName;
    }

    public function setMiddleName ($middleName)
    {
        $this->middleName = $middleName;
    }

    public function getLastName()
    {
        return $this->lastName;
    }

    public function setLastName ($lastName)
    {
        $this->lastName = $lastName;
    }

    public function getGroupId()
    {
        return $this->groupId;
    }

    public function setGroupId($value)
    {
        $this->groupId = $value;
    }

    public function getGroups()
    {
        if ($this->groups === \null) {
            $rel = new UsersGroupsRel();
            $this->groups = $rel->getUserGroups($this->getId());
        }

        return $this->groups;
    }

    public function getCurrentGroup()
    {
        return $this->currentGroup;
    }

    public function setCurrentGroup($currentGroup)
    {
        $this->currentGroup = $currentGroup;
    }

    public function getRoleId()
    {
        return '#' . $this->getUsername();
    }

    public function getDomainSettings()
    {
        return ['domain_depended' => 0];
    }

    public function verifyPassword($password)
    {
        if (empty($password) || empty($this->password)) {
            return false;
        }

        $password = sha1($password);

        return Security::verify($password, $this->password);
    }

    public function getForceLogout()
    {
        return $this->forceLogout;
    }

    public function setForceLogout($forceLogout)
    {
        $this->forceLogout = $forceLogout;
    }

    public function getLastLoginTime()
    {
        return $this->lastLoginTime;
    }

    public function setLastLoginTime($lastLoginTime)
    {
        $this->lastLoginTime = $lastLoginTime;
    }

    public function getRefreshTime()
    {
        return $this->refreshTime;
    }

    public function setRefreshTime($refreshTime)
    {
        $this->refreshTime = $refreshTime;
    }

    public function getFileSettings()
    {
        $settings = [
            'image' => [
                'label' => 'Снимка',
                'required' => 0,
                'validators' => [
                    'extension' => [
                        'validator' => 'File\Extension',
                        'options'   => [
                            'accept' => ['image/gif', 'image/png', 'image/bmp', 'image/jpeg'],
                        ],
                    ],
                ],
            ],
        ];

        return $settings;
    }

    /**
     * @return the $auth2fa
     */
    public function getAuth2fa ()
    {
        return $this->auth2fa;
    }

    /**
     * @param field_type $auth2fa
     */
    public function setAuth2fa ($auth2fa)
    {
        $this->auth2fa = $auth2fa;
    }

    /**
     * @return the $auth2faSecret
     */
    public function getAuth2faSecret ()
    {
        return $this->auth2faSecret;
    }

    /**
     * @param field_type $auth2faSecret
     */
    public function setAuth2faSecret ($auth2faSecret)
    {
        $this->auth2faSecret = $auth2faSecret;
    }
}