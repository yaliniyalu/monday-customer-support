<?php

namespace App\Application\Middleware;

use App\Service\MondayClient;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Exception\HttpUnauthorizedException;

class AuthenticateWithMondayOAuthToken implements Middleware
{
    private MondayClient $monday;

    public function __construct(MondayClient $monday)
    {
        $this->monday = $monday;
    }

    /**
     * {@inheritdoc}
     * @throws HttpUnauthorizedException
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        $params = $request->getQueryParams();

        $token = $params['token'] ?? ($params['state'] ?? null);
        if (!$token) {
            throw new HttpUnauthorizedException($request, "Token not found");
        }

        try {
            $token = $this->monday->verifyToken($token, $_ENV['MONDAY_SIGNING_SECRET']);
            $request = $request->withAttribute('mondayToken', $token);
        } catch (\Exception $e) {
            throw new HttpUnauthorizedException($request, "Token verification failed");
        }

        return $handler->handle($request);
    }
}
