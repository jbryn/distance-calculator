<?php

declare(strict_types=1);

namespace Jbryn\DistanceCalculator\Tests\Http;

use Jbryn\DistanceCalculator\Config\Settings;
use Jbryn\DistanceCalculator\Service\DistanceCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Sends requests through the whole application: middleware, routing, controllers and error handling.
 */
class ApiTest extends TestCase
{
    use SendsRequests;

    private const VALID_BODY = '{"lat1": 0, "lon1": 0, "lat2": 0, "lon2": 180}';

    public function testHealthCheck(): void
    {
        $response = $this->request('GET', '/');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame(['status' => 'API is working!'], $this->json($response));
    }

    public function testCalculatesDistanceFromJsonBody(): void
    {
        $response = $this->request('POST', '/api/calculate-distance', self::VALID_BODY);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame(
            ['status' => 'success', 'data' => ['meters' => 20015086.8, 'kilometers' => 20015.09]],
            $this->json($response),
        );
    }

    public function testRejectsInvalidCoordinates(): void
    {
        $response = $this->request(
            'POST',
            '/api/calculate-distance',
            '{"lat1": 91, "lon1": 0, "lat2": 0, "lon2": 0}',
        );

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame(
            [
                'status' => 'error',
                'message' => 'Invalid latitude for lat1. Value must be between -90 and 90 degrees',
            ],
            $this->json($response),
        );
    }

    #[DataProvider('unusableBodies')]
    public function testRejectsBodyThatIsNotAJsonObject(?string $body): void
    {
        $response = $this->request('POST', '/api/calculate-distance', $body);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame(
            ['status' => 'error', 'message' => 'Request body must be a JSON object'],
            $this->json($response),
        );
    }

    /**
     * @return array<array<mixed>>
     */
    public static function unusableBodies(): array
    {
        return [
            'no body' => [null],
            'malformed JSON' => ['{"lat1": 1,'],
            'JSON string' => ['"hello"'],
        ];
    }

    public function testUnknownPathReturnsNotFound(): void
    {
        $response = $this->request('GET', '/nope');

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame(['status' => 'error', 'message' => 'Not found.'], $this->json($response));
    }

    public function testWrongMethodReturnsMethodNotAllowed(): void
    {
        $response = $this->request('GET', '/api/calculate-distance');

        $this->assertSame(405, $response->getStatusCode());
        $this->assertSame('POST', $response->getHeaderLine('Allow'));
        $this->assertSame('error', $this->json($response)['status']);
    }

    public function testPreflightRequestIsAnsweredWithoutRouting(): void
    {
        $response = $this->request('OPTIONS', '/api/calculate-distance');

        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('', (string) $response->getBody());
        $this->assertSame('http://localhost:5173', $response->getHeaderLine('Access-Control-Allow-Origin'));
        $this->assertSame('Content-Type', $response->getHeaderLine('Access-Control-Allow-Headers'));
        $this->assertSame('GET, POST, OPTIONS', $response->getHeaderLine('Access-Control-Allow-Methods'));
    }

    #[DataProvider('everyKindOfResponse')]
    public function testEveryResponseCarriesCorsHeaders(string $method, string $path, ?string $body): void
    {
        $response = $this->request($method, $path, $body);

        $this->assertSame('http://localhost:5173', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    /**
     * @return array<array<mixed>>
     */
    public static function everyKindOfResponse(): array
    {
        return [
            'success' => ['POST', '/api/calculate-distance', self::VALID_BODY],
            'validation error' => ['POST', '/api/calculate-distance', '{}'],
            'not found' => ['GET', '/nope', null],
            'method not allowed' => ['GET', '/api/calculate-distance', null],
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
            'POST',
            '/api/calculate-distance',
            self::VALID_BODY,
            new Settings(debug: false, logErrors: false),
            [DistanceCalculator::class => $this->failingCalculator()],
        );

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame(['status' => 'error', 'message' => 'Internal server error'], $this->json($response));
        $this->assertSame('http://localhost:5173', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    public function testUnexpectedErrorIsShownWhenDebugIsOn(): void
    {
        $response = $this->request(
            'POST',
            '/api/calculate-distance',
            self::VALID_BODY,
            new Settings(debug: true, logErrors: false),
            [DistanceCalculator::class => $this->failingCalculator()],
        );

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame(['status' => 'error', 'message' => 'Something broke'], $this->json($response));
    }

    private function failingCalculator(): DistanceCalculator
    {
        return new class extends DistanceCalculator {
            public function calculate(float $lat1, float $lon1, float $lat2, float $lon2): array
            {
                throw new RuntimeException('Something broke');
            }
        };
    }
}
