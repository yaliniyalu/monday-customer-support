<?php

namespace App\Application\Actions\Authorization;

use App\Domain\DomainException\TokenNotFoundException;
use App\Service\MondayClient;
use App\Service\MondayTokenService;
use Lcobucci\JWT\Token;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;

class MondayOAuthRedirect extends \App\Application\Actions\Action
{
    private MondayTokenService $tokenStoreService;

    public function __construct(LoggerInterface $logger, MondayTokenService $tokenStoreService)
    {
        parent::__construct($logger);
        $this->tokenStoreService = $tokenStoreService;
    }

    /**
     * @inheritDoc
     */
    protected function action(): Response
    {
        /** @var Token $token */
        $token = $this->request->getAttribute('mondayToken');
        $userId = $token->getClaim('userId');

        try {
            $this->tokenStoreService->findByUser($userId);
        } catch (TokenNotFoundException $e) {
            return $this->redirect(MondayClient::MONDAY_OAUTH_URL . '?' . http_build_query([
                    'client_id' => $_ENV['MONDAY_CLIENT_ID'],
                    'state' => (string) $token
                ]));
        }

        return $this->redirect('/oauth/accounts?' . http_build_query(['token' => (string) $token]));
    }
}
