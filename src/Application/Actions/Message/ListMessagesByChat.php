<?php

namespace App\Application\Actions\Message;

use App\Application\Actions\Action;
use App\Domain\Chat\Provider\Provider;
use Psr\Http\Message\ResponseInterface as Response;

class ListMessagesByChat extends Action
{
    protected function action(): Response
    {
        /** @var Provider $chatProvider */
        $chatProvider = $this->request->getAttribute('chatProvider');

        if ($this->request->getQueryParams()['unread'] ?? false) {
            $messages = $chatProvider->getUnreadMessagesByChat($this->args['chatId']);
        } else {
            $messages = $chatProvider->getAllMessagesByChat($this->args['chatId']);
        }

        return $this->respondWithData($messages);
    }
}
