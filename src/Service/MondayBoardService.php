<?php

namespace App\Service;

use App\Domain\ChatAccount;
use App\Domain\DomainException\DomainRecordNotFoundException;
use App\Domain\MondayBoard;

class MondayBoardService
{
    private \MysqliDb $db;

    public function __construct(\MysqliDb $db)
    {
        $this->db = $db;
    }

    public function findByBoardId(int $boardId): MondayBoard
    {
        $data = $this->db
            ->where('board_id', $boardId)
            ->getOne('monday_boards');

        if (!$this->db->count) {
            throw new \Exception("Id not exists");
        }

        return new MondayBoard($data['board_id'], $data['account_id'], $data['user_id'], $data['chat_account_id']);
    }

    public function findByBoardIdWithChatAccount(int $boardId): MondayBoard
    {
        $data = $this->db
            ->where('b.board_id', $boardId)
            ->join('chat_accounts a', "a.id = b.chat_account_id")
            ->getOne('monday_boards b', 'a.*, b.*');

        if (!$this->db->count) {
            throw new DomainRecordNotFoundException();
        }

        $account = ChatAccountService::getChatAccountFromArray($data);

        $board = new MondayBoard($data['board_id'], $data['account_id'], $data['user_id'], $data['chat_account_id']);
        $board->setChatAccount($account);
        return $board;
    }

    public function save(MondayBoard $board)
    {
        $update = [
            'board_id' => $board->getBoardId(),
            'user_id' => $board->getUserId(),
            'account_id' => $board->getAccountId(),
            'chat_account_id' => $board->getChatAccountId()
        ];

        $this->db
            ->onDuplicate(['chat_account_id'])
            ->insert('monday_boards', $update);
    }
}
