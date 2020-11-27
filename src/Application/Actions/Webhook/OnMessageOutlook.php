<?php

namespace App\Application\Actions\Webhook;

use App\Application\Actions\Action;
use App\Domain\Chat\Provider\Gmail;
use App\Domain\Chat\Provider\Outlook;
use App\Service\ChatAccountService;
use App\Service\ChatProviderService;
use App\Service\MondayTriggerService;
use App\Service\NotificationClient;
use Base64Url\Base64Url;
use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;

class OnMessageOutlook extends Action
{
    private ChatAccountService $chatAccountService;
    private ChatProviderService $chatProviderService;
    private MondayTriggerService $mondayTriggerService;

    public function __construct(
        LoggerInterface $logger,
        ChatAccountService $chatAccountService,
        ChatProviderService $chatProviderService,
        MondayTriggerService $mondayTriggerService
    ) {
        parent::__construct($logger);
        $this->chatAccountService = $chatAccountService;
        $this->chatProviderService = $chatProviderService;
        $this->mondayTriggerService = $mondayTriggerService;
    }

    /**
     * @inheritDoc
     */
    protected function action(): Response
    {
        $queryParams = $this->request->getQueryParams();
        if (isset($queryParams['validationToken'])) {
            $decodedToken = urldecode($queryParams['validationToken']);
            $this->response->getBody()->write($decodedToken);
            return $this->response
                ->withHeader('Content-Type', 'text/plain')
                ->withStatus(StatusCodeInterface::STATUS_OK);
        }

        $values = $this->getFormData()['value'];

        $client = new NotificationClient();

        foreach ($values as $body) {
            $subId = $body['subscriptionId'];
            $account = $this->chatAccountService->findBySubscriptionId($subId);

            /** @var Outlook $chatProvider */
            $chatProvider = $this->chatProviderService->get($account);

            if (isset($body['lifecycleEvent'])) {
                if ($body['lifecycleEvent'] == 'subscriptionRemoved') {
                    try {
                        $subId = $chatProvider->subscribe();
                        $account->setIsSubscribed(true);
                        $account->setSubscriptionId($subId);
                    } catch (\Exception $e) {
                        $account->setIsSubscribed(false);
                        $account->setSubscriptionId(null);
                    }
                    $this->chatAccountService->update($account);
                } elseif ($body['lifecycleEvent'] == 'missed') {
                    goto process_notification;
                } elseif ($body['lifecycleEvent'] == 'reauthorizationRequired') {
                    $chatProvider->reauthorizeSubscription($subId);
                }

                return $this->response->withStatus(StatusCodeInterface::STATUS_OK);
            }

            process_notification:

            $time = time();
            $messages = $chatProvider->getMessagesFromNotification($body, $account->getProcessedId());
            if (!count($messages)) {
                continue;
            }

            $triggerMessages = array_filter(
                $messages,
                fn($message) => $message->getFrom()->getId() != $account->getId()
            );

            foreach ($triggerMessages as $triggerMessage) {
                $this->mondayTriggerService->processTrigger($account, $triggerMessage);
            }

            $messages = array_filter($messages, fn($message) => !$message->isRead());

            $account->setProcessedId($time);
            $this->chatAccountService->update($account);

            $response = [
                'to' => 'chat_account',
                'id' => $account->getId(),
                'message' => $messages
            ];

            $client->send($response);
        }


        $client->close();


        return $this->response->withStatus(StatusCodeInterface::STATUS_OK);
    }
}
