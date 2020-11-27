<?php

namespace App\Service;

class MondayActionService
{
    private MondayClient $mondayClient;
    private MondayTokenService $mondayTokenService;
    private ChatAccountService $chatAccountService;
    private ChatProviderService $chatProviderService;


    public function __construct(
        MondayClient $mondayClient,
        MondayTokenService $mondayTokenService,
        ChatAccountService $chatAccountService,
        ChatProviderService $chatProviderService
    ) {
        $this->mondayClient = $mondayClient;
        $this->mondayTokenService = $mondayTokenService;
        $this->chatAccountService = $chatAccountService;
        $this->chatProviderService = $chatProviderService;
    }

    public function createItem(array $data): void
    {
        $variables = [
            'boardId' => $data['boardId'],
            'item_name' => $data['subject'],
            'column_values' => json_encode([
                $data['emailColumnId'] => $data['email'] . " " . $data['name'],
                $data['chatColumnId'] => $data['chatId']
            ])
        ];

        if (isset($data['groupId'])) {
            $variables['group_id'] = $data['groupId'];
        }

        $query = <<<'QUERY'
mutation($boardId: Int!, $item_name: String, $group_id: String, $column_values: JSON) {
    create_item (board_id: $boardId, item_name: $item_name, group_id: $group_id, column_values: $column_values) {
        id
    }
}
QUERY;

        $this->mondayClient->setToken($this->mondayTokenService->findByUser($data['userId']));
        $this->mondayClient->query($query, $variables);
    }

    public function setLabel(array $data): void
    {
        $query = <<<'QUERY'
            query($items: [Int], $columns: [String]) {
                items(ids: $items) {
                  id,
                  column_values(ids: $columns) {
                    id,
                    text
                  }
                }
            }
QUERY;

        $variables = [
            'items' => [$data['itemId']],
            'columns' => [$data['chatColumnId'], $data['statusColumnId']]
        ];

        $this->mondayClient->setToken($this->mondayTokenService->findByUser($data['userId']));
        $response = $this->mondayClient->query($query, $variables);

        $column_values = json_decode((string) $response->getBody(), true)['data']['items'][0]['column_values'];

        $columns = [];
        foreach ($column_values as $column) {
            $columns[$column['id']] = $column['text'];
        }

        $chatId = $columns[$data['chatColumnId']];
        $label = $columns[$data['statusColumnId']];

        $account = $this->chatAccountService->findByBoardId($data['boardId']);
        $chatProvider = $this->chatProviderService->get($account);

        if ($data['applyLabelType']['value'] == 'add') {
            $chatProvider->addLabel($chatId, $label);
            return;
        }

        $query = $query = <<<'QUERY'
            query($boardIds: [Int], $columns: [String]) {
                boards(ids: $boardIds) {
                    columns(ids: $columns) {
                        id,
                        settings_str
                    }
                }
            }
QUERY;

        $variables = [
            'boardIds' => [$data['boardId']],
            'columns' => [$data['statusColumnId']]
        ];

        $response = $this->mondayClient->query($query, $variables);
        $labels = json_decode(
            json_decode((string) $response->getBody(), true)['data']['boards'][0]['columns'][0]['settings_str'],
            true
        )['labels'];

        $removeLabels = [];
        foreach ($labels as $l) {
            if ($l != $label) {
                $removeLabels[] = $l;
            }
        }

        $chatProvider->setLabel($chatId, $label, $labels);
    }
}
