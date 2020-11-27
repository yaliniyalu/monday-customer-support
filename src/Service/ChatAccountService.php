<?php

namespace App\Service;

use App\Domain\ChatAccount;
use App\Domain\DomainException\ChatAccountNotFoundException;

class ChatAccountService
{
    private \MysqliDb $db;

    public function __construct(\MysqliDb $db)
    {
        $this->db = $db;
    }

    public function findById(string $id): ChatAccount
    {
        $data = $this->db
            ->where('id', $id)
            ->getOne('chat_accounts');

        if (!$this->db->count) {
            throw new ChatAccountNotFoundException("Chat Account not linked");
        }

        return self::getChatAccountFromArray($data);
    }

    public function findByBoardId(int $boardId): ChatAccount
    {
        $data = $this->db
            ->where('b.board_id', $boardId)
            ->join('chat_accounts a', "a.id = b.chat_account_id")
            ->getOne('monday_boards b', 'a.*');

        if (!$this->db->count) {
            throw new ChatAccountNotFoundException("Chat Account not linked");
        }

        return self::getChatAccountFromArray($data);
    }

    public function findBySubscriptionId(string $id): ChatAccount
    {
        $data = $this->db
            ->where('subscription_id', $id)
            ->getOne('chat_accounts');

        if (!$this->db->count) {
            throw new ChatAccountNotFoundException("Chat Account not linked");
        }

        return self::getChatAccountFromArray($data);
    }

    public function deleteById(string $id)
    {
        $this->db
            ->where('id', $id)
            ->delete('chat_accounts', 1);
    }

    public function update(ChatAccount $account)
    {
        $update = self::getArrayFromChatAccount($account);
        $this->db
            ->where('id', $account->getId())
            ->update('chat_accounts', $update);
    }

    public function save(ChatAccount $account)
    {
        $update = self::getArrayFromChatAccount($account);
        $this->db
            ->onDuplicate(['refresh_token'])
            ->insert('chat_accounts', $update);
    }

    private static function getArrayFromChatAccount(ChatAccount $account): array
    {
        return [
            'id' => $account->getId(),
            'name' => $account->getName(),
            'user_id' => $account->getUserId(),
            'type' => $account->getType(),
            'access_token' => $account->getAccessToken(),
            'refresh_token' => $account->getRefreshToken(),
            'token_expires' => $account->getExpiry(),
            'processed_id' => $account->getProcessedId(),
            'subscription_id' => $account->getSubscriptionId(),
            'subscription_expires' => $account->getSubscriptionExpiry(),
            'is_subscribed' => $account->isSubscribed()
        ];
    }

    public static function getChatAccountFromArray(array $data): ChatAccount
    {
        $account = new ChatAccount($data['id'], $data['name'], $data['user_id']);
        $account->setType($data['type']);
        $account->setAccessToken($data['access_token']);
        $account->setRefreshToken($data['refresh_token']);
        $account->setExpiry((int) $data['token_expires']);
        $account->setProcessedId($data['processed_id']);
        $account->setSubscriptionId($data['subscription_id']);
        $account->setSubscriptionExpiry($data['subscription_expires']);
        $account->setIsSubscribed($data['is_subscribed']);
        return $account;
    }
}
