<?php

namespace App\Application\Actions\Message;

use App\Application\Actions\Action;
use App\Domain\Chat\Provider\Provider;
use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseInterface as Response;

class GetAttachment extends Action
{

    protected function action(): Response
    {
        /** @var Provider $chatProvider */
        $chatProvider = $this->request->getAttribute('chatProvider');

        $messageId = $this->args['messageId'];
        $attachmentId = $this->args['attachmentId'];

        $attachment = $chatProvider->getAttachment($messageId, $attachmentId);

        return $this->response
            ->withBody($attachment->getData())
            ->withHeader('content-type', $attachment->getMime())
            ->withHeader('content-disposition', 'attachment; filename="' . $attachment->getName() . '"')
            ->withStatus(StatusCodeInterface::STATUS_OK);
    }
}
