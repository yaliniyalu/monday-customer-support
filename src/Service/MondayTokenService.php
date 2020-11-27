<?php

namespace App\Service;

use App\Domain\DomainException\TokenNotFoundException;

class MondayTokenService
{
    private \MysqliDb $mysql;

    public function __construct(\MysqliDb $mysql)
    {
        $this->mysql = $mysql;
    }

    public function findByUser($userId)
    {
        $token = $this->mysql
            ->where('user_id', $userId)
            ->getValue('monday_accounts', 'access_token');

        if (!$this->mysql->count) {
            throw new TokenNotFoundException("Monday token not found");
        }

        return $token;
    }

    public function findByAccount($accountId)
    {
        $token =  $this->mysql
            ->where('account_id', $accountId)
            ->getValue('monday_accounts', 'access_token');

        if (!$this->mysql->count) {
            throw new TokenNotFoundException("Monday token not found");
        }

        return $token;
    }

    public function storeToken($userId, $accountId, $accessToken)
    {
        $result = $this->mysql
            ->onDuplicate(['access_token'])
            ->insert('monday_accounts', [
                'user_id' => $userId,
                'account_id' => $accountId,
                'access_token' => $accessToken
            ]);

        if (!$result) {
            throw new \Exception('Database Exception');
        }
    }
}
