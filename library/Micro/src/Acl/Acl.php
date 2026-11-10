<?php

namespace Micro\Acl;

use Micro\Exception\Exception as CoreException;
use Micro\Helper\Files;
use Micro\Application\Utils;
use Micro\Application\Controller;

class Acl implements AclInterface
{
    protected $data;

    protected static $resolver;

    public function __construct(array $data = [])
    {
        if (empty($data)) {
            $this->data = static::$resolver !== \null ? static::$resolver->__invoke() : [];
        } else {
            $this->data = $data;
        }
    }

    /**
     * @param array $data
     * @return Acl
     */
    public function setData(array $data)
    {
        $this->data = $data;

        return $this;
    }

    /**
     * @param array $data
     * @return Acl
     */
    public function addData(array $data)
    {
        $this->data = Utils::merge($this->data, $data);

        return $this;
    }

    /**
     * @param string $role
     * @param string $resource
     * @param mixed $privilege
     * @return boolean
     */
    public function isAllowed($role, $resource, $privilege)
    {
        if (empty($this->data) || !isset($this->data[$role])) {
            return \false;
        }

        $item = $this->data[$role];

        if (isset($item['resources'][$resource]) || \array_key_exists($resource, $item['resources'])) {
            if ($privilege === \null || \in_array($privilege, (array) $item['resources'][$resource], \true)) {
                return \true;
            }
        }

        // try parents

        if (!isset($item['parent'])) {
            return \false;
        }

        foreach ((array) $item['parent'] as $parent) {
            if (isset($this->data[$parent])) {
                if ($this->isAllowed($parent, $resource, $privilege)) {
                    return \true;
                }
            }
        }

        return \false;
    }

    public function getAllRoles($role)
    {
        if (!isset($this->data[$role])) {
            return [];
        }

        $item = $this->data[$role];

        $roles[$role] = $role;

        if (isset($item['parent'])) {
            $parents = (array) $item['parent'];
            foreach ($parents as $parent) {
                if (isset($this->data[$parent])) {
                    $roles += $this->getAllRoles($parent);
                }
            }
        }

        return $roles;
    }

    /**
     * Call
     * @param callable $resolver
     */
    public static function setResolver(callable $resolver)
    {
        static::$resolver = $resolver;
    }

    /**
     * @param object $object
     * @param string $action
     * @throws CoreException
     * @return Acl
     */
    public function checkForAccess($object, $action)
    {
        if (!\is_object($object)) {
            return;
        }

        $docMethod = Files::parseDocBlock(
            (new \ReflectionMethod($object, $action))->getDocComment()
        );

        if ($object instanceof Controller) { // remove "Action" suffix
            $action = \substr($action, 0, -6);
        }

        // Authorize logic
        if (!\array_key_exists('NotAuthorize', $docMethod)) {
            if ($object instanceof AuthorizeInterface || \array_key_exists('AuthorizeMethod', $docMethod)) {
                if ((bool) \is_allowed(\get_class($object) . '@' . $action) === \false) {
                    throw new CoreException(\sprintf('Access denied for resource [%s]', \get_class($object) . '@' . $action), 403);
                }
            }
            if (\array_key_exists('AuthorizeRight', $docMethod)) {
                $decoded = \json_decode($docMethod['AuthorizeRight'], \true);
                if ($decoded !== \null) {
                    foreach ((array) $decoded as $right) {
                        if ((bool) \is_allowed($right) === \false) {
                            throw new CoreException(\sprintf('Access denied for right [%s]', $right), 403);
                        }
                    }
                }
            }
        }

        return $this;
    }
}