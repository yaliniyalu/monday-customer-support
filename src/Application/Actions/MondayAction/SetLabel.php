<?php

namespace App\Application\Actions\MondayAction;

use App\Service\MondayActionService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;

class SetLabel extends \App\Application\Actions\Action
{
    private MondayActionService $mondayActionService;

    public function __construct(
        LoggerInterface $logger,
        MondayActionService $mondayActionService
    ) {
        parent::__construct($logger);
        $this->mondayActionService = $mondayActionService;
    }

    /**
     * @inheritDoc
     */
    protected function action(): Response
    {
        $body = $this->getFormData()['payload']['inputFields'];
        $this->mondayActionService->setLabel($body);

        return $this->respondWithData([]);
    }
}
