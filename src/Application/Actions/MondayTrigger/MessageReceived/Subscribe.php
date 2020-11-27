<?php

namespace App\Application\Actions\MondayTrigger\MessageReceived;

use App\Application\Actions\Action;
use App\Domain\DomainException\ChatAccountNotFoundException;
use App\Domain\MondayTrigger;
use App\Service\ChatAccountService;
use App\Service\ChatAccountSubscriptionService;
use App\Service\MondayTriggerService;
use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;

class Subscribe extends Action
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
        $body = $body['payload'];

        $params = $this->request->getQueryParams();

        $trigger = new MondayTrigger();
        $trigger->setType($params['type']);
        $trigger->setBoardId($body['inputFields']['boardId']);
        $trigger->setSubscriptionId($body['subscriptionId']);
        $trigger->setWebhookUrl($body['webhookUrl']);
        $trigger->setData($body['inputFields']);

        $trigger = $this->mondayTriggerService->subscribe($trigger);

        try {
            $this->chatAccountSubscriptionService->subscribe($trigger);
        } catch (ChatAccountNotFoundException $e) {
        }

        $this->response->getBody()->write(json_encode(['webhookId' => $trigger->getId()]));
        return $this->response
            ->withStatus(StatusCodeInterface::STATUS_OK)
            ->withAddedHeader('Content-Type', 'application/json');
    }
}
