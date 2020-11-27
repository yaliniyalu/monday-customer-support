<?php

namespace App\Application\Middleware;

use App\Service\AuthenticationService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Exception\HttpUnauthorizedException;

class AuthenticateWithUserAuthToken implements Middleware
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
        $token = null;

        $header = $request->getHeader('Authorization');
        if (!empty($header[0])) {
            $token = str_replace('Bearer ', '', $header[0]);
        } else {
            $token = $request->getQueryParams()['token'] ?? null;
        }

        if (!$token) {
            throw new HttpUnauthorizedException($request, "Invalid token");
        }

        try {
            $token = $this->auth->verifyToken($token);
        } catch (\Exception $e) {
            throw new HttpUnauthorizedException($request, "Token verification failed");
        }

        $request = $request->withAttribute('accessToken', $token);
        return $handler->handle($request);
    }
}
