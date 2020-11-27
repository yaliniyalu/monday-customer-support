<?php

namespace App\Application\Middleware;

use App\Domain\DomainException\DomainRecordNotFoundException;
use App\Service\ChatAccountService;
use App\Service\ChatProviderService;
use Lcobucci\JWT\Token;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Exception\HttpInternalServerErrorException;

class LoadChatProvider implements Middleware
{
    private ChatAccountService $chatAccountService;
    private ChatProviderService $chatProviderService;

    public function __construct(ChatAccountService $chatAccountService, ChatProviderService $chatProviderService)
    {
        $this->chatAccountService = $chatAccountService;
        $this->chatProviderService = $chatProviderService;
    }

    /**
     * {@inheritdoc}
     * @throws HttpInternalServerErrorException
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        /** @var Token $token */
        $token = $request->getAttribute('accessToken');

        try {
            $chatAccount = $this->chatAccountService->findByBoardId((int) $token->getClaim('board_id'));
        } catch (DomainRecordNotFoundException $e) {
            throw new HttpInternalServerErrorException($request, "Unable to get access token");
        }

        $chatProvider = $this->chatProviderService->get($chatAccount);

        $request = $request
            ->withAttribute('chatProvider', $chatProvider)
            ->withAttribute('chatAccount', $chatAccount);

        return $handler->handle($request);
    }
}
