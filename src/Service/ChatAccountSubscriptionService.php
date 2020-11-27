<?php

namespace App\Service;

use App\Domain\DomainException\ChatAccountNotFoundException;
use App\Domain\MondayTrigger;

class ChatAccountSubscriptionService
{
    private ChatAccountService $chatAccountService;
    private ChatProviderService $chatProviderService;

    public function __construct(ChatAccountService $chatAccountService, ChatProviderService $chatProviderService)
    {
        $this->chatAccountService = $chatAccountService;
        $this->chatProviderService = $chatProviderService;
    }

    /**
     * @param MondayTrigger $trigger
     * @throws ChatAccountNotFoundException
     */
    public function subscribe(MondayTrigger $trigger)
    {
        $account = $this->chatAccountService->findByBoardId($trigger->getBoardId());
        if ($account->isSubscribed()) {
            return;
        }

        $provider = $this->chatProviderService->get($account);
        $id = $provider->subscribe();

        $account->setProcessedId($provider->getInitialSubscriptionState());
        $account->setSubscriptionId($id);
        $account->setIsSubscribed(true);
        $this->chatAccountService->update($account);
    }

    public function unsubscribe(MondayTrigger $trigger)
    {
        $account = $this->chatAccountService->findByBoardId($trigger->getBoardId());
        if (!$account->isSubscribed()) {
            return;
        }

        $provider = $this->chatProviderService->get($account);
        $provider->unsubscribe($account->getSubscriptionId());

        $account->setSubscriptionId(null);
        $account->setIsSubscribed(false);
        $this->chatAccountService->update($account);
    }
}
