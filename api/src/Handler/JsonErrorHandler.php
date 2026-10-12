<?php

declare(strict_types=1);

namespace Jbryn\DistanceCalculator\Handler;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpException;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Interfaces\ErrorHandlerInterface;
use Throwable;

final class JsonErrorHandler implements ErrorHandlerInterface
{
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
    ) {}

    public function __invoke(
        ServerRequestInterface $request,
        Throwable $exception,
        bool $displayErrorDetails,
        bool $logErrors,
        bool $logErrorDetails,
    ): ResponseInterface {
        $isHttpException = $exception instanceof HttpException;

        // HTTP errors (404, 405, ...) are the client's doing; anything else is a bug worth logging
        if (!$isHttpException && $logErrors) {
            error_log((string) $exception);
        }

        $status = $isHttpException ? $exception->getCode() : 500;
        $message = $isHttpException || $displayErrorDetails
            ? $exception->getMessage()
            : 'Internal server error';

        $response = $this->responseFactory->createResponse($status);
        $response->getBody()->write(json_encode([
            'status' => 'error',
            'message' => $message,
        ], JSON_THROW_ON_ERROR));

        if ($exception instanceof HttpMethodNotAllowedException) {
            $response = $response->withHeader('Allow', implode(', ', $exception->getAllowedMethods()));
        }

        return $response->withHeader('Content-Type', 'application/json');
    }
}
