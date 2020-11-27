<?php

namespace App\Application\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

class DecodeCustomerId implements Middleware
{
    /**
     * {@inheritdoc}
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        $encodedCustomerId = $request->getAttributes()['__routingResults__']->getRouteArguments()['encodedCustomerId'];
        $request = $request->withAttribute('customerId', base64_decode($encodedCustomerId));
        return $handler->handle($request);
    }
}
