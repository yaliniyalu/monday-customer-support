<?php

namespace App\Application\Actions\Authorization;

use App\Domain\Chat\User;
use App\Domain\ChatAccount;
use App\Domain\MondayBoard;
use App\Domain\MondayTrigger;
use App\Service\ChatAccountService;
use App\Service\ChatAccountSubscriptionService;
use App\Service\ChatProviderService;
use App\Service\MondayBoardService;
use App\Service\MondayTriggerService;
use Lcobucci\JWT\Token;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;

class OAuthCallback extends \App\Application\Actions\Action
{
    private ChatAccountService $chatAccountService;
    private ChatAccountSubscriptionService $chatAccountSubscriptionService;
    private ChatProviderService $chatProviderService;
    private MondayBoardService $mondayBoardService;
    private MondayTriggerService $mondayTriggerService;

    public function __construct(
        LoggerInterface $logger,
        ChatAccountService $chatAccountService,
        ChatAccountSubscriptionService $chatAccountSubscriptionService,
        ChatProviderService $chatProviderService,
        MondayBoardService $mondayBoardService,
        MondayTriggerService $mondayTriggerService
    ) {
        parent::__construct($logger);
        $this->chatAccountService = $chatAccountService;
        $this->chatAccountSubscriptionService = $chatAccountSubscriptionService;
        $this->chatProviderService = $chatProviderService;
        $this->mondayBoardService = $mondayBoardService;
        $this->mondayTriggerService = $mondayTriggerService;
    }

    protected function action(): Response
    {
        /** @var Token $token */
        $token = $this->request->getAttribute('mondayToken');

        $userId = $token->getClaim('userId');
        $boardId = $token->getClaim('boardId');
        $accountId = $token->getClaim('accountId');
        $backToUrl = $token->getClaim('backToUrl');

        $query = $this->request->getQueryParams();

        if (isset($query['error'])) {
            return $this->redirect($backToUrl);
        }

        /** @var AbstractProvider $oauthClient */
        $oauthClient = $this->request->getAttribute('oauthClient');

        try {
            $accessToken = $oauthClient->getAccessToken('authorization_code', [
                'code' => $query['code']
            ]);
        } catch (IdentityProviderException $e) {
            return $this->redirect($backToUrl);
        }

        $providerType = $this->resolveArg('provider');

        $providerMap = ['google' => 'gmail', 'microsoft' => 'outlook'];

        $provider = $this->chatProviderService->getByType($providerMap[$providerType], $accessToken->getToken());
        $user = $provider->getAccountUserInfo();

        $account = new ChatAccount($user->getId(), $user->getName(), $userId);
        $account->setType($providerMap[$providerType]);
        $account->setRefreshToken($accessToken->getRefreshToken());
        $account->setAccessToken($accessToken->getToken());
        $account->setExpiry($accessToken->getExpires());
        $account->setProcessedId(null);
        $account->setSubscriptionId(null);
        $account->setSubscriptionExpiry(null);
        $account->setIsSubscribed(false);

        $board = new MondayBoard($boardId, $accountId, $userId, $user->getId());

        $this->chatAccountService->save($account);
        $this->mondayBoardService->save($board);

        try {
            if ($this->mondayTriggerService->hasTriggerForBoard($board->getBoardId())) {
                $trigger = new MondayTrigger();
                $trigger->setBoardId($board->getBoardId());
                $this->chatAccountSubscriptionService->subscribe($trigger);
            }
        } catch (\Exception $e) {
        }

        return $this->redirect($backToUrl);
    }
}
