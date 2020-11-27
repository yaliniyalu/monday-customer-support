<?php

namespace App\Application\Actions\Authorization;

use App\Service\MondayClient;
use App\Service\MondayTokenService;
use Lcobucci\JWT\Token;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;

class MondayOAuthCallback extends \App\Application\Actions\Action
{
    private MondayClient $monday;
    private MondayTokenService $tokenStoreService;

    public function __construct(LoggerInterface $logger, MondayClient $monday, MondayTokenService $tokenStoreService)
    {
        parent::__construct($logger);
        $this->monday = $monday;
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
        $accountId = $token->getClaim('accountId');
        $backToUrl = $token->getClaim('backToUrl');

        $query = $this->request->getQueryParams();

        if (isset($query['error'])) {
            return $this->redirect($backToUrl);
        }

        $token = $this->monday->getOAuthToken($query['code'], $_ENV['MONDAY_CLIENT_ID'], $_ENV['MONDAY_CLIENT_SECRET']);
        $this->tokenStoreService->storeToken($userId, $accountId, $token['access_token']);

        return $this->redirect('/oauth/accounts?' . http_build_query(['token' => $query['state']]));
    }
}
