<?php
declare(strict_types=1);

use Slim\Exception\HttpException;
use Slim\Factory\AppFactory;
use DI\ContainerBuilder;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

require __DIR__ . '/../vendor/autoload.php';

// Loading environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// Reads from the .env file or from real environment variables (e.g. in a container)
$env = fn (string $key, string $default): string =>
    $_ENV[$key] ?? $_SERVER[$key] ?? (getenv($key) ?: $default);

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
    bool $displayErrorDetails
) use ($app) {
    $isHttpException = $exception instanceof HttpException;
    $status = $isHttpException ? $exception->getCode() : 500;
    $message = $isHttpException || $displayErrorDetails
        ? $exception->getMessage()
        : 'Internal server error';

    $response = $app->getResponseFactory()->createResponse();
    $response->getBody()->write(json_encode([
        'status' => 'error',
        'message' => $message
    ]));

    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus($status);
});

// CORS handling (added last so it also wraps error responses)
$app->add(function (Request $request, $handler) use ($corsOrigin) {
    $response = $handler->handle($request);
    return $response
        ->withHeader('Access-Control-Allow-Origin', $corsOrigin)
        ->withHeader('Access-Control-Allow-Headers', 'Content-Type')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
});


$app->post('/api/calculate-distance', [
    \Jbryn\DistanceCalculator\Controller\DistanceController::class,
    'calculate'
]);


$app->get('/', function (Request $request, Response $response) {
    $response->getBody()->write(json_encode(['status' => 'API is working!']));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->run();