<?php

namespace App\Application\Middleware;

use DI\Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Exception\HttpInternalServerErrorException;
use Slim\Exception\HttpNotFoundException;

class LoadOAuthProvider implements Middleware
{
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * {@inheritdoc}
     * @throws HttpInternalServerErrorException
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        $provider = $request->getAttributes()['__routingResults__']->getRouteArguments()['provider'];

        if ($provider == 'google') {
            $oauthClient = $this->container->get(\League\OAuth2\Client\Provider\Google::class);
        } elseif ($provider == 'microsoft') {
            $oauthClient = $this->container->get(\Stevenmaguire\OAuth2\Client\Provider\Microsoft::class);
        } else {
            throw new HttpNotFoundException($request, "The requested url not found");
        }

        $request = $request
            ->withAttribute('oauthClient', $oauthClient);

        return $handler->handle($request);
    }
}
