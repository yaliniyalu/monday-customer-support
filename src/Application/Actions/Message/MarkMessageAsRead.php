<?php

namespace App\Application\Actions\Message;

use App\Domain\Chat\Provider\Provider;
use Psr\Http\Message\ResponseInterface as Response;

class MarkMessageAsRead extends \App\Application\Actions\Action
{
    /**
     * @inheritDoc
     */
    protected function action(): Response
    {
        /** @var Provider $chatProvider */
        $chatProvider = $this->request->getAttribute('chatProvider');
        $messageId = $this->args['messageId'];

        $messages = $chatProvider->markAsRead([$messageId]);

        return $this->respondWithData($messages);
    }
}
