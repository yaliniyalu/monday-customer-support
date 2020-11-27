<?php

namespace App\Application\Actions\MondayTrigger\MessageReceived;

use App\Application\Actions\Action;
use App\Service\ChatAccountSubscriptionService;
use App\Service\MondayTriggerService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;

class Unsubscribe extends Action
{
    private MondayTriggerService $mondayTriggerService;
    private ChatAccountSubscriptionService $chatAccountSubscriptionService;

    public function __construct(
        LoggerInterface $logger,
        MondayTriggerService $mondayTriggerService,
        ChatAccountSubscriptionService $chatAccountSubscriptionService
    ) {
        parent::__construct($logger);
        $this->mondayTriggerService = $mondayTriggerService;
        $this->chatAccountSubscriptionService = $chatAccountSubscriptionService;
    }

    protected function action(): Response
    {
        $body = $this->getFormData();

        $trigger = $this->mondayTriggerService->findById($body['payload']['webhookId']);
        $this->mondayTriggerService->unsubscribe($trigger);

        try {
            if (!$this->mondayTriggerService->hasTriggerForBoard($trigger->getBoardId())) {
                $this->chatAccountSubscriptionService->unsubscribe($trigger);
            }
        } catch (\Exception $e) {
        }

        return $this->respondWithData([]);
    }
}
