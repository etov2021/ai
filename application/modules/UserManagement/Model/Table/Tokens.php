<?php

namespace UserManagement\Model\Table;

use Micro\Database\Table\TableAbstract;
use Micro\JWT\JWT;
use Micro\Math\Rand;
use Micro\Database\Expr;

class Tokens extends TableAbstract
{
    protected $_name = 'UsersTokens';

    public function generateCode($userId)
    {
        $code = $this->getNewCode($userId);
        $token = JWT::encode(['id' => $userId, 'exp' => time() + config('jwt.auth_exp', 3600)], config('jwt.key'));

        $this->insert(['userId' => $userId, 'code' => $code, 'token' => $token]);

        return $code;
    }

    protected function getNewCode($userId)
    {
        $code = Rand::getString(6);

        if ($this->fetchRow(['userId = ?' => $userId, new Expr('BINARY `code` = ' . $this->getAdapter()->quote($code))])) {
            return $this->getNewCode($userId);
        }

        return $code;
    }

    public function checkCode($userId, $code)
    {
        try {

            $row = $this->fetchRow(['userId = ?' => $userId, new Expr('BINARY `code` = ' . $this->getAdapter()->quote($code))]);

            if (!empty($row)) {
                $decoded = JWT::decode($row->token, config('jwt.key'));
                if ($decoded->id != $userId) {
                    raise('Invalid token data');
                }
                $row->delete();
                return true;
            } else {
                raise('Invalid token');
            }

        } catch (\Throwable $e) {

            flash()->setMessage($e->getMessage(), 'danger');
        }

        return false;
    }
}