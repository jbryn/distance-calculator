<?php
declare(strict_types=1);

use Slim\Factory\AppFactory;
use DI\ContainerBuilder;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

require __DIR__ . '/../vendor/autoload.php';

// Ładowanie zmiennych środowiskowych
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Konfiguracja kontenera DI
$containerBuilder = new ContainerBuilder();
$containerBuilder->addDefinitions(__DIR__ . '/../src/Config/dependencies.php');
$container = $containerBuilder->build();

// Utworzenie aplikacji
$app = AppFactory::createFromContainer($container);

// Dodanie middleware
$app->addBodyParsingMiddleware();

// Obsługa CORS
$app->add(function (Request $request, $handler) {
    $response = $handler->handle($request);
    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'Content-Type')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
});

// Obsługa błędów
$errorMiddleware = $app->addErrorMiddleware(true, true, true);
$errorMiddleware->setDefaultErrorHandler(function (
    Request $request,
    Throwable $exception
) use ($app) {
    $response = $app->getResponseFactory()->createResponse();
    $response->getBody()->write(json_encode([
        'error' => $exception->getMessage()
    ]));

    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(400);
});

// Trasy
$app->post('/api/calculate-distance', [
    \Jbryn\DistanceCalculator\Controller\DistanceController::class,
    'calculate'
]);

// Trasa testowa
$app->get('/', function (Request $request, Response $response) {
    $response->getBody()->write(json_encode(['status' => 'API is working!']));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->run();