<?php

namespace App\Application\Middleware;

use App\Service\AuthenticationService;
use Lcobucci\JWT\Token;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Exception\HttpUnauthorizedException;

class AuthorizeBoardAccess implements Middleware
{
    private AuthenticationService $auth;

    public function __construct(AuthenticationService $auth)
    {
        $this->auth = $auth;
    }

    /**
     * {@inheritdoc}
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        /** @var Token $token */
        $token = $request->getAttribute('accessToken');

        $boardId_1 = $token->getClaim('board_id');
        $boardId_2 = $request->getAttributes()['__routingResults__']->getRouteArguments()['boardId'];

        if ($boardId_1 != $boardId_2) {
            throw new HttpUnauthorizedException($request, "Token invalid for the board");
        }

        return $handler->handle($request);
    }
}
