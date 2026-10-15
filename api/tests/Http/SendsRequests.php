<?php

declare(strict_types=1);

namespace Jbryn\Downriver\Tests\Http;

use Jbryn\Downriver\AppFactory;
use Jbryn\Downriver\Config\Settings;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Factory\ServerRequestFactory;

/**
 * Sends a request through the whole application and reads the JSON that comes back.
 */
trait SendsRequests
{
    /**
     * @param string               $path        may include a query string
     * @param array<string, mixed> $definitions container entries that replace the defaults
     */
    private function request(
        string $method,
        string $path,
        ?string $body = null,
        ?Settings $settings = null,
        array $definitions = [],
    ): ResponseInterface {
        $request = (new ServerRequestFactory())->createServerRequest($method, $path);

        $query = parse_url($path, PHP_URL_QUERY);
        if (is_string($query)) {
            parse_str($query, $parameters);
            $request = $request->withQueryParams($parameters);
        }

        if ($body !== null) {
            $request = $request->withHeader('Content-Type', 'application/json');
            $request->getBody()->write($body);
        }

        return AppFactory::create($settings ?? new Settings(logErrors: false), $definitions)->handle($request);
    }

    /**
     * @return array<mixed>
     */
    private function json(ResponseInterface $response): array
    {
        $data = json_decode((string) $response->getBody(), true);
        self::assertIsArray($data);

        return $data;
    }
}
