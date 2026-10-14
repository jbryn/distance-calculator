<?php

declare(strict_types=1);

namespace Jbryn\DistanceCalculator\Tests\Http;

use Jbryn\DistanceCalculator\Config\Settings;
use Jbryn\DistanceCalculator\Overpass\OverpassClient;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Behaviour shared by every endpoint: routing errors, CORS and error handling.
 */
class ApiTest extends TestCase
{
    use SendsRequests;

    private const RIVER = '/api/rivers/8974372';

    public function testHealthCheck(): void
    {
        $response = $this->request('GET', '/');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame(['status' => 'API is working!'], $this->json($response));
    }

    public function testUnknownPathReturnsNotFound(): void
    {
        $response = $this->request('GET', '/nope');

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame(['status' => 'error', 'message' => 'Not found.'], $this->json($response));
    }

    public function testTheRemovedStraightLineEndpointIsGone(): void
    {
        $this->assertSame(404, $this->request('POST', '/api/calculate-distance', '{}')->getStatusCode());
    }

    public function testWrongMethodReturnsMethodNotAllowed(): void
    {
        $response = $this->request('POST', '/api/rivers', '{}');

        $this->assertSame(405, $response->getStatusCode());
        $this->assertSame('GET', $response->getHeaderLine('Allow'));
        $this->assertSame('error', $this->json($response)['status']);
    }

    public function testPreflightRequestIsAnsweredWithoutRouting(): void
    {
        $response = $this->request('OPTIONS', self::RIVER);

        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('', (string) $response->getBody());
        $this->assertSame('http://localhost:5173', $response->getHeaderLine('Access-Control-Allow-Origin'));
        $this->assertSame('Content-Type', $response->getHeaderLine('Access-Control-Allow-Headers'));
        $this->assertSame('GET, OPTIONS', $response->getHeaderLine('Access-Control-Allow-Methods'));
    }

    #[DataProvider('everyKindOfResponse')]
    public function testEveryResponseCarriesCorsHeaders(string $method, string $path): void
    {
        $response = $this->request($method, $path);

        $this->assertSame('http://localhost:5173', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function everyKindOfResponse(): array
    {
        return [
            'success' => ['GET', '/api/rivers'],
            'not found' => ['GET', '/nope'],
            'method not allowed' => ['DELETE', self::RIVER],
        ];
    }

    public function testCorsOriginComesFromSettings(): void
    {
        $settings = new Settings(corsOrigin: 'https://example.com', logErrors: false);

        $response = $this->request('GET', '/', settings: $settings);

        $this->assertSame('https://example.com', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    public function testUnexpectedErrorIsHiddenWhenDebugIsOff(): void
    {
        $response = $this->request(
            'GET',
            self::RIVER,
            settings: new Settings(debug: false, logErrors: false),
            definitions: [OverpassClient::class => $this->brokenOverpassClient()],
        );

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame(['status' => 'error', 'message' => 'Internal server error'], $this->json($response));
        $this->assertSame('http://localhost:5173', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    public function testUnexpectedErrorIsShownWhenDebugIsOn(): void
    {
        $response = $this->request(
            'GET',
            self::RIVER,
            settings: new Settings(debug: true, logErrors: false),
            definitions: [OverpassClient::class => $this->brokenOverpassClient()],
        );

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame(['status' => 'error', 'message' => 'Something broke'], $this->json($response));
    }

    /**
     * Fails in a way the application does not expect, unlike an unavailable server.
     */
    private function brokenOverpassClient(): OverpassClient
    {
        return new class implements OverpassClient {
            public function query(string $query): array
            {
                throw new RuntimeException('Something broke');
            }
        };
    }
}
