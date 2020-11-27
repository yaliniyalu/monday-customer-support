<?php

namespace App\Application\Actions\User;

use App\Domain\DomainException\DomainRecordNotFoundException;
use App\Service\AuthenticationService;
use App\Service\MondayBoardService;
use App\Service\MondayClient;
use App\Service\MondayTokenService;
use Lcobucci\JWT\Token;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpNotFoundException;
use Slim\Exception\HttpUnauthorizedException;

class GenerateToken extends \App\Application\Actions\Action
{
    private $monday;
    private $authenticationService;
    private $mondayBoardService;
    private $mondayTokenService;

    public function __construct(
        LoggerInterface $logger,
        MondayClient $monday,
        AuthenticationService $authenticationService,
        MondayBoardService $mondayBoardService,
        MondayTokenService $mondayTokenService
    ) {
        parent::__construct($logger);

        $this->monday = $monday;
        $this->authenticationService = $authenticationService;
        $this->mondayBoardService = $mondayBoardService;
        $this->mondayTokenService = $mondayTokenService;
    }

    /**
     * @inheritDoc
     */
    protected function action(): Response
    {
        /** @var Token $token */
        $token = $this->request->getAttribute('mondaySessionToken');
        $dat = $token->getClaim('dat');

        $boardId = (int) $this->args['boardId'];
        $accountId = (int) $dat->account_id;
        $userId = (int) $dat->user_id;

        // check if account contains the board provided
        try {
            $board = $this->mondayBoardService->findByBoardIdWithChatAccount($boardId);
        } catch (DomainRecordNotFoundException $e) {
            throw new HttpNotFoundException($this->request, "Board Not Found");
        }

        if ($board->getAccountId() != $accountId) {
            throw new HttpUnauthorizedException($this->request, "Permission denied for board");
        }

        // check account owners monday token
        $mondayToken = $this->mondayTokenService->findByUser($board->getUserId());

        if (!$mondayToken) {
            throw new HttpUnauthorizedException($this->request, "Monday Token Not Found");
        }

        $this->monday->setToken($mondayToken, '');

        // check if current user has board access
        $res = $this->monday->query("
            query {
                boards(ids: [$boardId]) {
                    owner {
                        id
                    },
                    board_kind,
                    permissions,
                    subscribers {
                        id,
                        name,
                        enabled,
                        is_view_only,
                        is_guest
                    }
                }
            }");

        $boards = json_decode((string) $res->getBody(), true);
        if (isset($boards['errors']) || !count($boards['data']['boards'])) {
            throw new HttpBadRequestException($this->request, "Could not load board");
        }

        $b = $boards['data']['boards'][0];

        $permission = $this->getUsersPermissionForBoard($userId, $b);
        if (!$permission) {
            throw new HttpUnauthorizedException($this->request, "You dont have permission to the board");
        }

        $isBoardOwner = $b['owner']['id'] == $userId;

        $chatAccount = $board->getChatAccount();

        $data = [
            'board_id' => $boardId,
            'account_id' => $accountId,
            'user_id' => $userId,
            'owner_id' => $board->getUserId(),
            'chat_account_id' => $chatAccount->getId(),
            'is_board_owner' => $isBoardOwner,
            'board_permission' => $permission
        ];

        $token = $this->authenticationService->generateToken($data);

        $response = [
            'token' => (string) $token,
            'monday' => $data,
            'chat_account' => [
                'id' => $chatAccount->getId(),
                'name' => $chatAccount->getName()
            ],
            'permission' => [
                'is_owner' => $isBoardOwner,
                'permission' => $permission
            ],
            'me' => [
                'id' => $userId,
                'board_id' => $boardId,
                'account_id' => $accountId
            ]
        ];

        return $this->respondWithData($response);
    }

    private function getUsersPermissionForBoard($userId, $board)
    {
        if ($board['owner']['id'] == $userId) {
            return 'edit';
        }

        if ($board['board_kind'] == "public") {
            if (in_array($board['permissions'], ['everyone', 'collaborators'])) {
                return 'edit';
            }

            if ($board['permissions'] == 'assignee') {
                return 'assignee';
            }

            return 'view';
        }

        foreach ($board['subscribers'] as $subscriber) {
            if ($subscriber['id'] == $userId) {
                if (!$subscriber['enabled']) {
                    return null;
                }

                if ($subscriber['is_view_only'] || $subscriber['is_guest'] || $board['board_kind'] == "share") {
                    return 'view';
                }

                if (in_array($board['permissions'], ['everyone', 'collaborators'])) {
                    return 'edit';
                }

                if ($board['permissions'] == 'assignee') {
                    return 'assignee';
                }

                return 'view';
            }
        }

        return null;

        //everyone, collaborators, assignee, owners
    }
}
