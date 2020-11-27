<?php

declare(strict_types=1);

namespace App\Application\Actions\RenderHTML;

use App\Application\Actions\ActionPayload;
use App\Domain\DomainException\DomainRecordNotFoundException;
use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpNotFoundException;
use Slim\Views\Twig;

abstract class Action
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var Twig
     */
    protected $twig;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var Response
     */
    protected $response;

    /**
     * @var array
     */
    protected $args;

    /**
     * @param LoggerInterface $logger
     * @param Twig $twig
     */
    public function __construct(LoggerInterface $logger, Twig $twig)
    {
        $this->logger = $logger;
        $this->twig = $twig;
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     * @throws HttpNotFoundException
     * @throws HttpBadRequestException
     */
    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $this->request = $request;
        $this->response = $response;
        $this->args = $args;

        try {
            return $this->action();
        } catch (DomainRecordNotFoundException $e) {
            throw new HttpNotFoundException($this->request, $e->getMessage());
        }
    }

    /**
     * @return Response
     * @throws DomainRecordNotFoundException
     * @throws HttpBadRequestException
     */
    abstract protected function action(): Response;

    /**
     * @param array|object|null $data
     * @param string $template
     * @param int $statusCode
     * @return Response
     * @throws \Exception
     */
    protected function respondWithData(string $template, $data = null, int $statusCode = 200): Response
    {
        $payload = new ActionPayload($statusCode, $data);
        return $this->respond($template, $payload);
    }

    /**
     * @param string $template
     * @param ActionPayload $payload
     * @return Response
     * @throws \Exception
     */
    protected function respond(string $template, ActionPayload $payload): Response
    {
        $this->response = $this->response
            ->withHeader('Content-Type', 'text/html')
            ->withStatus($payload->getStatusCode());

        return $this->twig->render($this->response, $template, $payload->getData());
    }

    protected function redirect($url, $status = StatusCodeInterface::STATUS_FOUND)
    {
        return $this->response
            ->withAddedHeader('Location', $url)
            ->withStatus($status);
    }
}
