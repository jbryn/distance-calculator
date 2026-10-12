<?php

declare(strict_types=1);

namespace Jbryn\DistanceCalculator;

use DI\Container;
use DI\ContainerBuilder;
use Jbryn\DistanceCalculator\Config\Settings;
use Jbryn\DistanceCalculator\Controller\DistanceController;
use Jbryn\DistanceCalculator\Handler\JsonErrorHandler;
use Jbryn\DistanceCalculator\Middleware\CorsMiddleware;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Factory\AppFactory as SlimAppFactory;

final class AppFactory
{
    /**
     * Builds the fully configured application without running it,
     * so tests can send requests through the same setup as production.
     *
     * @param array<string, mixed> $definitions container entries that replace the defaults
     *
     * @return App<Container>
     */
    public static function create(Settings $settings, array $definitions = []): App
    {
        $containerBuilder = new ContainerBuilder();
        $containerBuilder->addDefinitions(__DIR__ . '/Config/dependencies.php');
        $containerBuilder->addDefinitions([Settings::class => $settings]);
        $containerBuilder->addDefinitions($definitions);

        $app = SlimAppFactory::createFromContainer($containerBuilder->build());

        // Middleware runs in reverse order of registration: the last one added is the outermost
        $app->addBodyParsingMiddleware();

        $errorMiddleware = $app->addErrorMiddleware($settings->debug, $settings->logErrors, $settings->logErrors);
        $errorMiddleware->setDefaultErrorHandler(new JsonErrorHandler($app->getResponseFactory()));

        // Outermost, so that error responses carry CORS headers too
        $app->add(new CorsMiddleware($settings->corsOrigin, $app->getResponseFactory()));

        self::registerRoutes($app);

        return $app;
    }

    /**
     * @param App<Container> $app
     */
    private static function registerRoutes(App $app): void
    {
        $app->get('/', function (Request $request, Response $response): Response {
            $response->getBody()->write(json_encode(['status' => 'API is working!'], JSON_THROW_ON_ERROR));

            return $response->withHeader('Content-Type', 'application/json');
        });

        $app->post('/api/calculate-distance', DistanceController::class . ':calculate');
    }
}
