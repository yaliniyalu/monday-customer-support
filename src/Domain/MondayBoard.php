<?php

namespace App\Domain;

class MondayBoard
{
    private int $boardId;
    private int $accountId;
    private int $userId;
    private string $chatAccountId;

    private ?ChatAccount $chatAccount;

    public function __construct(int $boardId, int $accountId, int $userId, string $chatAccountId)
    {
        $this->boardId = $boardId;
        $this->accountId = $accountId;
        $this->userId = $userId;
        $this->chatAccountId = $chatAccountId;
    }

    /**
     * @return int
     */
    public function getBoardId(): int
    {
        return $this->boardId;
    }

    /**
     * @return int
     */
    public function getAccountId(): int
    {
        return $this->accountId;
    }

    /**
     * @return int
     */
    public function getUserId(): int
    {
        return $this->userId;
    }

    /**
     * @return string
     */
    public function getChatAccountId(): string
    {
        return $this->chatAccountId;
    }

    /**
     * @return ChatAccount
     */
    public function getChatAccount(): ?ChatAccount
    {
        return $this->chatAccount;
    }

    /**
     * @param ChatAccount|null $chatAccount
     */
    public function setChatAccount(?ChatAccount $chatAccount): void
    {
        $this->chatAccount = $chatAccount;
    }
}
