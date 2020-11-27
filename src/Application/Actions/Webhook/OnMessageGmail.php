<?php

namespace App\Application\Actions\Webhook;

use App\Application\Actions\Action;
use App\Domain\Chat\Provider\Gmail;
use App\Service\ChatAccountService;
use App\Service\MondayBoardService;
use App\Service\MondayTriggerService;
use Base64Url\Base64Url;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;

class OnMessageGmail extends Action
{
    private ChatAccountService $chatAccountService;
    private MondayTriggerService $mondayTriggerService;
    private \Google_Client $googleClient;

    public function __construct(
        LoggerInterface $logger,
        ChatAccountService $chatAccountService,
        MondayTriggerService $mondayTriggerService,
        \Google_Client $googleClient
    ) {
        parent::__construct($logger);
        $this->chatAccountService = $chatAccountService;
        $this->mondayTriggerService = $mondayTriggerService;
        $this->googleClient = $googleClient;
    }

    /**
     * @inheritDoc
     */
    protected function action(): Response
    {
        $body = $this->getFormData();
        $data = json_decode(Base64Url::decode($body['message']['data']), true);

        $email = $data['emailAddress'];
        $account = $this->chatAccountService->findById($email);

        $this->googleClient->refreshToken($account->getRefreshToken());
        $chatProvider = new Gmail($this->googleClient);

        $messages = $chatProvider->getMessagesFromNotification($data, $account->getProcessedId());
        if (!count($messages)) {
            return $this->respondWithData([], 200);
        }

        $triggerMessages = array_filter($messages, fn($message) => $message->getFrom()->getId() != $email);
        foreach ($triggerMessages as $triggerMessage) {
            $this->mondayTriggerService->processTrigger($account, $triggerMessage);
        }

        $last = end($messages);
        $account->setProcessedId($last->getAttribute('historyId'));
        $this->chatAccountService->update($account);

        $messages = array_filter($messages, fn($message) => !$message->isRead());

        $response = [
            'to' => 'chat_account',
            'id' => $email,
            'message' => $messages
        ];

        $client = new \WebSocket\Client("ws://127.0.0.1:8089/?type=server&auth_token=" . $_ENV['MY_SIGNING_SECRET']);
        $client->send(json_encode($response));
        $client->close();

        return $this->respondWithData([], 200);
    }
}
