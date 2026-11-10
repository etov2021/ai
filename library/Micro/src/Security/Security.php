<?php

namespace Micro\Security;

class Security
{
    public static function hash($password, $algo = \PASSWORD_DEFAULT, array $options = [])
    {
        return \password_hash($password, $algo, $options);
    }

    public static function verify($password, $hash)
    {
        return \password_verify($password, $hash);
    }

    public static function rehash($hash, $algo = \PASSWORD_DEFAULT, array $options = [])
    {
        return \password_needs_rehash($hash, $algo, $options);
    }
}