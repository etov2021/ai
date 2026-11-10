<?php

namespace UserManagement;

use Micro\Application\Module as BaseModule;
use Micro\Container\ContainerInterface;
use Micro\Acl\Acl;
use Micro\Cache\Core as Cache;
use Micro\Database\Adapter\AdapterAbstract;
use UserManagement\Model\Entity\User;
use Micro\Auth\Auth;
use UserManagement\Model\Users;
use Micro\Acl\AclInterface;
use Micro\Event\Message;
use Micro\Auth\Identity;
use Micro\Http\Response\RedirectResponse;
use UserManagement\Model\Table\Users as TableUsers;
use Micro\JWT\JWT;

class Module extends BaseModule
{
    public function boot(ContainerInterface $container)
    {
        $config = $container->get('config');

        Auth::setResolver(function ($identity) use ($config) {

            if (!isset($identity['user']) || !$identity['user'] instanceof Identity) {
                return \null;
            }

            $user = $identity['user'];

            if (config('auth.2fa') && $user->getAuth2fa() && isset($identity['step']) && $identity['step'] < 2) {
                $ip2fa = config('2fa.ip', []);
                if (!in_array(user_ip(), $ip2fa) && !$this->hasAccessToken($identity['user'])) {
                    $sess = Auth::getInstance()->getStorage()->getName();
                    if ($sess == 'admin_auth') {
                        return \null;
                    }
                }
            }

            switch ($config->get('auth.resolver.strategy')) {
                case 'query' :
                    $user = (new Users())->find((int) $user->getId());
                    break;
            }

            return $user;
        });

        Acl::setResolver(function () use ($container) {

            $db = app('db', true);

            if (!$db instanceof AdapterAbstract) {
                return [];
            }

            $cache = cache('json');

            if (!$cache instanceof Cache || ($data = $cache->load('Acl')) === false) {

                $groups = $db->fetchAll('
                    SELECT a.alias, b.alias as parentAlias, a.rights, a.customRights
                    FROM UsersGroups a
                    LEFT JOIN UsersGroups b ON b.id = a.parentId
                ');

                $data = [];

                foreach ($groups as $group) {

                    $data[$group['alias']] = [
                        'parent'    => $group['parentAlias'],
                        'resources' => []
                    ];

                    $rights = $group['rights'] ? \json_decode($group['rights'], \true) : [];
                    $rights = \is_array($rights) ? $rights : [];

                    $customRights = $group['customRights'] ? \json_decode($group['customRights'], \true) : [];
                    $customRights = \is_array($customRights) ? $customRights : [];
                    if (!empty($customRights)) {
                        $customRights = \array_combine($customRights, \array_fill(0, \count($customRights), \true));
                    }

                    $data[$group['alias']]['resources'] = $rights + $customRights;
                }

                if ($cache instanceof Cache) {
                    $cache->save($data, 'Acl', ['Acl']);
                }

            }

            // индивидуални потребителски права
            $identity = Auth::identity();

            if ($identity instanceof User && !isset($data[$identity->getRoleId()])) {

                $rights = $identity->getRights();
                $rights = $rights ? \json_decode($identity->getRights(), \true) : [];

                $customRights = $identity->getCustomRights();
                $customRights = $customRights ? \json_decode($customRights, \true) : [];

                if (!empty($customRights)) {
                    $customRights = \array_combine($customRights, \array_fill(0, \count($customRights), \true));
                }

                $data[$identity->getRoleId()] = [
                    'parent'    => $identity->getCurrentGroup() ? $identity->getCurrentGroup() : $identity->getGroups(),
                    'resources' => $rights + $customRights,
                ];
            }

            return $data;
        });

        $container->get('event')->attach('Dispatch.Start', function (Message $message) use ($container) {

            $identity = Auth::identity();

            if (config('auth.refresh_time', false) && $identity) {
                (new TableUsers())->update(['refreshTime' => date('Y-m-d H:i:s')], ['id = ?' => $identity->getId()]);
            }

            if (config('auth.force_logout', false)) {
                if ($identity && $identity->getForceLogout()) {
                    (new Users())->forceLogout($identity);
                    return new RedirectResponse(route_reset('admin-login'));
                }
            }

            $acl = $container->get('acl');

            if ($acl instanceof AclInterface) {
                $acl->checkForAccess($message->handler, $message->action);
            }
        });
    }

    protected function hasAccessToken($user)
    {
        if (isset($_COOKIE['access_token'])) {
            try {
                $payload = (new JWT())->decode($_COOKIE['access_token'], config('jwt.key'));
                if ($user->username == $payload->username) {
                    return true;
                }
            } catch (\Throwable $e) {

            }
        }

        return false;
    }
}
