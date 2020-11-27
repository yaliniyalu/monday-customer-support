<?php

namespace App\Service;

use App\Domain\Chat\Message;
use App\Domain\ChatAccount;
use App\Domain\DomainException\DomainRecordNotFoundException;
use App\Domain\MondayTrigger;

class MondayTriggerService
{
    private \MysqliDb $db;
    private ChatAccountService $chatAccountService;
    private ChatProviderService $chatProviderService;
    private MondayTokenService $mondayTokenService;
    private MondayClient $mondayClient;
    private MondayBoardService $mondayBoardService;

    public function __construct(
        \MysqliDb $db,
        MondayClient $mondayClient,
        MondayBoardService $mondayBoardService,
        MondayTokenService $mondayTokenService,
        ChatAccountService $chatAccountService,
        ChatProviderService $chatProviderService
    ) {
        $this->db = $db;
        $this->chatAccountService = $chatAccountService;
        $this->chatProviderService = $chatProviderService;
        $this->mondayClient = $mondayClient;
        $this->mondayBoardService = $mondayBoardService;
        $this->mondayTokenService = $mondayTokenService;
    }

    /**
     * @param int $id
     * @return MondayTrigger
     * @throws DomainRecordNotFoundException
     * @throws \Exception
     */
    public function findById(int $id): MondayTrigger
    {
        $data = $this->db
            ->where('id', $id)
            ->getOne('monday_triggers');

        if (!$this->db->count) {
            throw new DomainRecordNotFoundException();
        }

        return self::createMondayTriggerFromArray($data);
    }

    /**
     * @param string $id
     * @return MondayTrigger[]
     * @throws \Exception
     */
    public function findAllByBoardId(string $id): array
    {
        $items = $this->db
            ->where('board_id', $id)
            ->get('monday_triggers');

        $triggers = [];
        foreach ($items as $data) {
            $triggers[] = self::createMondayTriggerFromArray($data);
        }

        return $triggers;
    }

    /**
     * @param string $id
     * @return MondayTrigger[]
     * @throws \Exception
     */
    public function findAllByChatAccountId(string $id): array
    {
        $items = $this->db
            ->where('b.chat_account_id', $id)
            ->join('monday_boards b', 'b.board_id = t.board_id')
            ->get('monday_triggers t', null, 't.*');

        $triggers = [];
        foreach ($items as $data) {
            $triggers[] = self::createMondayTriggerFromArray($data);
        }

        return $triggers;
    }

    private static function createMondayTriggerFromArray(array $data): MondayTrigger
    {
        $trigger = new MondayTrigger();
        $trigger->setId($data['id']);
        $trigger->setType($data['type']);
        $trigger->setBoardId($data['board_id']);
        $trigger->setSubscriptionId($data['subscription_id']);
        $trigger->setWebhookUrl($data['webhook_url']);
        $trigger->setData(json_decode($data['data'] ?? "[]", true));

        return $trigger;
    }

    /**
     * @param MondayTrigger $trigger
     * @return MondayTrigger
     * @throws DomainRecordNotFoundException
     * @throws \Exception
     */
    public function subscribe(MondayTrigger $trigger): MondayTrigger
    {
        $update = [
            'type' => $trigger->getType(),
            'subscription_id' => $trigger->getSubscriptionId(),
            'board_id' => $trigger->getBoardId(),
            'webhook_url' => $trigger->getWebhookUrl(),
            'data' => json_encode($trigger->getData())
        ];

        $id = $this->db
            ->insert('monday_triggers', $update);

        $trigger->setId($id);

        return $trigger;
    }

    /**
     * @param MondayTrigger $trigger
     * @throws \Exception
     */
    public function unsubscribe(MondayTrigger $trigger)
    {
        $this->db
            ->where('id', $trigger->getId())
            ->delete('monday_triggers');
    }

    public function hasTriggerForBoard(int $boardId)
    {
        $this->db
            ->where('board_id', $boardId)
            ->getOne('monday_triggers', '1');

        return $this->db->count > 0;
    }

    /**
     * @param ChatAccount $account
     * @param Message $message
     * @throws \Exception
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function processTrigger(ChatAccount $account, Message $message)
    {
        $token = $this->mondayTokenService->findByUser($account->getUserId());

        $triggers = $this->findAllByChatAccountId($account->getId());
        if (!count($triggers)) {
            return;
        }

        $cachedResult = [];
        foreach ($triggers as $trigger) {
            $statusColumn = $trigger->getData()['statusColumnId'] ?? '';

            $query = '
                query($board_id: Int!, $column_id: String!, $column_value: String!, $columns: [String]) {
                    items_by_column_values (
                        board_id: $board_id, 
                        column_id: $column_id, 
                        column_value: $column_value
                    ) {
                         id,
                         column_values(ids: $columns) {
                            id,
                            value
                        }
                    }
                }
';
            $variables = [
                'board_id' => $trigger->getBoardId(),
                'column_id' => $trigger->getData()['chatColumnId'],
                'column_value' => $message->getChatId(),
                'columns' => $statusColumn ? [$statusColumn] : []
            ];

            $key = md5($query);

            if (!isset($cachedResult[$key])) {
                $this->mondayClient->setToken($token);
                $response = $this->mondayClient->query($query, $variables);
                $data = json_decode((string) $response->getBody(), true);

                $cachedResult[$key] = $data['data']['items_by_column_values'];
            }

            $result = $cachedResult[$key];

            if ($trigger->getType() == 'new' && !count($result)) {
                $this->triggerCreateItem($trigger, $message);
                unset($cachedResult[$key]);
            } elseif ($trigger->getType() == 'existing' && count($result)) {
                foreach ($result as $item) {
                    $status = '';
                    foreach ($item['column_values'] as $column_value) {
                        if ($column_value['id'] == $statusColumn) {
                            $status = json_decode($column_value['value'], true)['index'];
                            break;
                        }
                    }

                    if ($status == $trigger->getData()['statusColumnValue']['index']) {
                        $this->triggerChangeStatus($trigger, $message, $item['id']);
                    }
                }
            }
        }
    }

    private function triggerCreateItem(MondayTrigger $trigger, Message $message)
    {
        $triggerData = $trigger->getData();

        $data = [
            'boardId' => $trigger->getBoardId(),
            'userId' => $triggerData['userId'],
            'chatId' => $message->getChatId(),
            'email' => $message->getFrom()->getId(),
            'name' => $message->getFrom()->getName(),
            'subject' => $message->getSubject(),
            'chatColumnId' => $triggerData['chatColumnId'],
            'emailColumnId' => $triggerData['emailColumnId']
        ];

        $this->mondayClient->setToken($_ENV['MONDAY_SIGNING_SECRET'], '');
        $this->mondayClient->trigger($trigger->getWebhookUrl(), $data);
    }

    private function triggerChangeStatus(MondayTrigger $trigger, Message $message, int $itemId)
    {
        $triggerData = $trigger->getData();

        $data = [
            'boardId' => $trigger->getBoardId(),
            'userId' => $triggerData['userId'],
            'itemId' => $itemId,
            'chatId' => $message->getChatId(),
            'chatColumnId' => $triggerData['chatColumnId'],
            'statusColumnId' => $triggerData['statusColumnId'],
            'statusColumnValue' => $triggerData['statusColumnValue']
        ];

        $this->mondayClient->setToken($_ENV['MONDAY_SIGNING_SECRET'], '');
        $this->mondayClient->trigger($trigger->getWebhookUrl(), $data);
    }
}
