<?php

namespace App\Application\Middleware;

use App\Service\MondayClient;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Exception\HttpUnauthorizedException;

class AuthenticateWithMondaySessionToken implements Middleware
{
    private MondayClient $monday;

    public function __construct(MondayClient $monday)
    {
        $this->monday = $monday;
    }

    /**
     * {@inheritdoc}
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        $token = $request->getHeader('Authorization');
        if (empty($token) || empty($token[0])) {
            throw new HttpUnauthorizedException($request, "Invalid token");
        }

        $token = str_replace('Bearer ', '', $token[0]);

        try {
            $token = $this->monday->verifyToken($token, $_ENV['MONDAY_CLIENT_SECRET']);
            $request = $request->withAttribute('mondaySessionToken', $token);
        } catch (\Exception $e) {
            throw new HttpUnauthorizedException($request, "Token verification failed");
        }

        return $handler->handle($request);
    }
}
