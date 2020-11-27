<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

class OptionsRequestHandlerMiddleware implements Middleware
{
    /**
     * {@inheritdoc}
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        if ($request->getMethod() == 'OPTIONS') {
            return new \Slim\Psr7\Response(StatusCodeInterface::STATUS_OK);
        }

        return $handler->handle($request);
    }
}
