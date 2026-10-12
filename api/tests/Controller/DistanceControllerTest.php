<?php

declare(strict_types=1);

namespace Jbryn\DistanceCalculator\Tests\Controller;

use Jbryn\DistanceCalculator\Controller\DistanceController;
use Jbryn\DistanceCalculator\Service\DistanceCalculator;
use Jbryn\DistanceCalculator\Service\Validator;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

class DistanceControllerTest extends TestCase
{
    private DistanceController $controller;

    protected function setUp(): void
    {
        $this->controller = new DistanceController(new DistanceCalculator(), new Validator());
    }

    public function testReturnsDistanceForValidRequest(): void
    {
        $response = $this->post(['lat1' => 0.0, 'lon1' => 0.0, 'lat2' => 0.0, 'lon2' => 180.0]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame(
            ['status' => 'success', 'data' => ['meters' => 20015086.8, 'kilometers' => 20015.09]],
            $this->json($response),
        );
    }

    public function testAcceptsIntegerAndStringCoordinates(): void
    {
        $response = $this->post(['lat1' => 52, 'lon1' => '21', 'lat2' => 52, 'lon2' => '21']);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(
            ['status' => 'success', 'data' => ['meters' => 0, 'kilometers' => 0]],
            $this->json($response),
        );
    }

    public function testReturnsBadRequestForInvalidCoordinates(): void
    {
        $response = $this->post(['lat1' => 91.0, 'lon1' => 0.0, 'lat2' => 0.0, 'lon2' => 0.0]);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame(
            [
                'status' => 'error',
                'message' => 'Invalid latitude for lat1. Value must be between -90 and 90 degrees',
            ],
            $this->json($response),
        );
    }

    public function testReturnsBadRequestForMissingBody(): void
    {
        $response = $this->post(null);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame(
            ['status' => 'error', 'message' => 'Request body must be a JSON object'],
            $this->json($response),
        );
    }

    /**
     * @param array<string, mixed>|null $body
     */
    private function post(?array $body): ResponseInterface
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/api/calculate-distance')
            ->withParsedBody($body);

        return $this->controller->calculate($request, new Response());
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
