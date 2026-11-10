<?php

namespace Micro\Acl;

use Micro\Exception\Exception as CoreException;

interface AclInterface
{
    /**
     * @param string $role
     * @param string $resource
     * @param mixed $privilege
     * @return boolean
     */
    public function isAllowed($role, $resource, $privilege);

    /**
     * @param object $object
     * @param string $action
     * @throws CoreException on not Access
     */
    public function checkForAccess($object, $action);
}