<?php

declare(strict_types=1);

namespace Jbryn\DistanceCalculator\Tests\Overpass;

use GuzzleHttp\Psr7\HttpFactory;
use InvalidArgumentException;
use Jbryn\DistanceCalculator\Overpass\HttpOverpassClient;
use Jbryn\DistanceCalculator\Overpass\OverpassUnavailableException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class HttpOverpassClientTest extends TestCase
{
    private const QUERY = '[out:json];relation(8974372);out geom;';
    private const FIRST = 'https://first.example/api/interpreter';
    private const SECOND = 'https://second.example/api/interpreter';
    private const ELEMENTS = [['type' => 'relation', 'id' => 8974372, 'tags' => ['name' => 'Gwda']]];

    private FakeHttpClient $http;

    protected function setUp(): void
    {
        $this->http = new FakeHttpClient();
    }

    public function testReturnsTheElementsOfTheResponse(): void
    {
        $this->http->respondWithElements(self::ELEMENTS);

        $this->assertSame(self::ELEMENTS, $this->client()->query(self::QUERY));
    }

    public function testSendsTheQueryAsAFormPostAndIdentifiesItself(): void
    {
        $this->http->respondWithElements([]);

        $this->client()->query(self::QUERY);

        $request = $this->http->requests[0];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame(self::FIRST, (string) $request->getUri());
        $this->assertSame('application/x-www-form-urlencoded', $request->getHeaderLine('Content-Type'));
        $this->assertSame('test-agent (contact@example.com)', $request->getHeaderLine('User-Agent'));

        parse_str((string) $request->getBody(), $form);
        $this->assertSame(['data' => self::QUERY], $form);
    }

    public function testDoesNotContactTheSecondServerWhenTheFirstAnswers(): void
    {
        $this->http->respondWithElements(self::ELEMENTS);

        $this->client()->query(self::QUERY);

        $this->assertCount(1, $this->http->requests);
    }

    #[DataProvider('failures')]
    public function testFallsBackToTheNextServer(callable $failFirstRequest): void
    {
        $failFirstRequest($this->http);
        $this->http->respondWithElements(self::ELEMENTS);

        $elements = $this->client()->query(self::QUERY);

        $this->assertSame(self::ELEMENTS, $elements);
        $this->assertCount(2, $this->http->requests);
        $this->assertSame(self::SECOND, (string) $this->http->requests[1]->getUri());
    }

    /**
     * @return array<array<mixed>>
     */
    public static function failures(): array
    {
        return [
            'gateway timeout' => [fn(FakeHttpClient $http) => $http->respondWith(504)],
            'rate limited' => [fn(FakeHttpClient $http) => $http->respondWith(429)],
            'network error' => [fn(FakeHttpClient $http) => $http->failWith('Connection timed out')],
            'HTML error page' => [fn(FakeHttpClient $http) => $http->respondWith(200, '<html>busy</html>')],
            'JSON without elements' => [fn(FakeHttpClient $http) => $http->respondWith(200, '{"version": 0.6}')],
            'element that is not an object' => [fn(FakeHttpClient $http) => $http->respondWith(200, '{"elements": [1]}')],
            'query ran out of time' => [
                fn(FakeHttpClient $http) => $http->respondWith(
                    200,
                    '{"elements": [], "remark": "runtime error: Query timed out in \"query\" at line 1 after 26 seconds."}',
                ),
            ],
        ];
    }

    public function testAnEmptyResultIsAnAnswerNotAFailure(): void
    {
        $this->http->respondWithElements([]);

        $this->assertSame([], $this->client()->query(self::QUERY));
        $this->assertCount(1, $this->http->requests);
    }

    public function testFailsWhenEveryServerFails(): void
    {
        $this->http->respondWith(504)->failWith('Connection timed out');

        try {
            $this->client()->query(self::QUERY);
            $this->fail('Expected OverpassUnavailableException');
        } catch (OverpassUnavailableException $e) {
            $this->assertStringContainsString('No Overpass server answered', $e->getMessage());
            $this->assertStringContainsString(self::FIRST . ': HTTP 504', $e->getMessage());
            $this->assertStringContainsString(self::SECOND . ': request failed (Connection timed out)', $e->getMessage());
        }

        $this->assertCount(2, $this->http->requests);
    }

    public function testRequiresAtLeastOneServer(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new HttpOverpassClient($this->http, new HttpFactory(), new HttpFactory(), [], 'test-agent');
    }

    private function client(): HttpOverpassClient
    {
        $factory = new HttpFactory();

        return new HttpOverpassClient(
            $this->http,
            $factory,
            $factory,
            [self::FIRST, self::SECOND],
            'test-agent (contact@example.com)',
        );
    }
}
