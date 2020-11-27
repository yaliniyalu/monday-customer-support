<?php

namespace App\Application\Actions\Message;

use App\Domain\Chat\Provider\Provider;
use App\Domain\Chat\User;
use App\Domain\ChatAccount;
use Psr\Http\Message\ResponseInterface as Response;

class ReplyMessage extends \App\Application\Actions\Action
{
    /**
     * @inheritDoc
     */
    protected function action(): Response
    {
        /** @var Provider $chatProvider */
        $chatProvider = $this->request->getAttribute('chatProvider');

        /** @var ChatAccount $chatAccount */
        $chatAccount = $this->request->getAttribute('chatAccount');

        $userId = $this->request->getAttribute('customerId');
        $chatId = $this->args['chatId'];
        $message = $this->getFormData()['message'];

        $from = new User($chatAccount->getId(), $chatAccount->getName());

        $message = $chatProvider->send($from, $userId, $message, $chatId);

        return $this->respondWithData([$message]);
    }
}
