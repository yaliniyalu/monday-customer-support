<?php

namespace App\Application\Actions\Authorization;

use App\Application\Actions\RenderHTML\Action;
use App\Domain\DomainException\DomainRecordNotFoundException;
use App\Service\ChatAccountService;
use App\Service\ChatProviderService;
use Lcobucci\JWT\Token;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;

class SelectOAuthAccounts extends Action
{
    private ChatAccountService $chatAccountService;
    private ChatProviderService $chatProviderService;

    public function __construct(
        LoggerInterface $logger,
        Twig $twig,
        ChatAccountService $chatAccountService,
        ChatProviderService $chatProviderService
    ) {
        parent::__construct($logger, $twig);
        $this->chatAccountService = $chatAccountService;
        $this->chatProviderService = $chatProviderService;
    }

    /**
     * @inheritDoc
     */
    protected function action(): Response
    {
        $query = $this->request->getQueryParams();

        /** @var Token $token */
        $token = $this->request->getAttribute('mondayToken');
        $boardId = (int) $token->getClaim('boardId');
        $backToUrl = $token->getClaim('backToUrl');

        $linkedAccount = null;
        try {
            $linkedAccount = $this->chatAccountService->findByBoardId($boardId);
            $token = $this->chatProviderService->fetchAccessToken($linkedAccount);

            if ($token->hasExpired()) {
                throw new \Exception();
            }

            return $this->redirect($backToUrl);
        } catch (\Exception $e) {
        }

        $data = [
            'linked_account' => $linkedAccount,
            'monday_token' => $query['token'],
            'board_id' => $boardId
        ];

        return $this->respondWithData('select-account.twig', $data);
    }
}
