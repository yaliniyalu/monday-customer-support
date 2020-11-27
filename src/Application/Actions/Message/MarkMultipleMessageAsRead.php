<?php

namespace App\Application\Actions\Message;

use App\Application\Actions\ActionError;
use App\Application\Actions\ActionPayload;
use App\Domain\Chat\Provider\Provider;
use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseInterface as Response;

class MarkMultipleMessageAsRead extends \App\Application\Actions\Action
{
    /**
     * @inheritDoc
     */
    protected function action(): Response
    {
        /** @var Provider $chatProvider */
        $chatProvider = $this->request->getAttribute('chatProvider');

        $data = $this->getFormData();
        if (empty($data['ids'])) {
            return $this->respond(
                new ActionPayload(
                    StatusCodeInterface::STATUS_BAD_REQUEST,
                    null,
                    new ActionError("Invalid Id", "Items ids cannot be empty")
                )
            );
        }

        $marked = $chatProvider->markAsRead($data['ids']);
        return $this->respondWithData($marked);
    }
}
