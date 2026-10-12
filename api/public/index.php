<?php

declare(strict_types=1);

use DI\ContainerBuilder;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Exception\HttpException;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

// Loading environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// Reads from the .env file or from real environment variables (e.g. in a container)
$env = static function (string $key, string $default): string {
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

    return is_string($value) && $value !== '' ? $value : $default;
};

$debug = filter_var($env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN);
$corsOrigin = $env('CORS_ORIGIN', 'http://localhost:5173');

// DI container configuration
$containerBuilder = new ContainerBuilder();
$containerBuilder->addDefinitions(__DIR__ . '/../src/Config/dependencies.php');
$container = $containerBuilder->build();

// Creating application
$app = AppFactory::createFromContainer($container);

// Adding middleware
$app->addBodyParsingMiddleware();

// Handle OPTIONS preflight requests
$app->options('/{routes:.+}', function (Request $request, Response $response) {
    return $response;
});

$errorMiddleware = $app->addErrorMiddleware($debug, true, true);
$errorMiddleware->setDefaultErrorHandler(function (
    Request $request,
    Throwable $exception,
    bool $displayErrorDetails,
) use ($app) {
    $isHttpException = $exception instanceof HttpException;
    $status = $isHttpException ? $exception->getCode() : 500;
    $message = $isHttpException || $displayErrorDetails
        ? $exception->getMessage()
        : 'Internal server error';

    $response = $app->getResponseFactory()->createResponse();
    $response->getBody()->write(json_encode([
        'status' => 'error',
        'message' => $message,
    ], JSON_THROW_ON_ERROR));

    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus($status);
});

// CORS handling (added last so it also wraps error responses)
$app->add(function (Request $request, RequestHandler $handler) use ($corsOrigin) {
    $response = $handler->handle($request);
    return $response
        ->withHeader('Access-Control-Allow-Origin', $corsOrigin)
        ->withHeader('Access-Control-Allow-Headers', 'Content-Type')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
});


$app->post(
    '/api/calculate-distance',
    \Jbryn\DistanceCalculator\Controller\DistanceController::class . ':calculate',
);


$app->get('/', function (Request $request, Response $response) {
    $response->getBody()->write(json_encode(['status' => 'API is working!'], JSON_THROW_ON_ERROR));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->run();
