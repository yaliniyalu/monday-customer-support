<?php

namespace App\Application\Middleware;

use App\Service\AuthenticationService;
use Lcobucci\JWT\Token;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Exception\HttpUnauthorizedException;

class AuthorizeBoardEdit implements Middleware
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

        $permission = $token->getClaim('board_permission');
        if ($permission != 'edit' && $permission != 'assignee') {
            throw new HttpUnauthorizedException($request, "You dont have edit access");
        }

        return $handler->handle($request);
    }
}
