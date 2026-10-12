<?php

declare(strict_types=1);

namespace Jbryn\DistanceCalculator\Tests\Overpass;

use Jbryn\DistanceCalculator\AppFactory;
use Jbryn\DistanceCalculator\Config\Settings;
use Jbryn\DistanceCalculator\Overpass\CachedOverpassClient;
use Jbryn\DistanceCalculator\Overpass\OverpassClient;
use Jbryn\DistanceCalculator\Overpass\OverpassUnavailableException;
use Jbryn\DistanceCalculator\Overpass\QueryCache;
use PHPUnit\Framework\TestCase;

/**
 * Checks that the application assembles the Overpass client from its settings.
 */
class ContainerWiringTest extends TestCase
{
    use TemporaryDirectory;

    private const QUERY = '[out:json];relation(8974372);out geom;';

    protected function setUp(): void
    {
        $this->createTemporaryDirectory();
    }

    protected function tearDown(): void
    {
        $this->removeTemporaryDirectory();
    }

    public function testTheApplicationProvidesACachedClient(): void
    {
        $this->assertInstanceOf(CachedOverpassClient::class, $this->clientFor(new Settings()));
    }

    public function testOfflineModeAnswersFromTheConfiguredDirectory(): void
    {
        $elements = [['type' => 'relation', 'id' => 8974372]];
        (new QueryCache($this->directory))->put(self::QUERY, $elements);

        $client = $this->clientFor(new Settings(overpassCacheDir: $this->directory, overpassOffline: true));

        $this->assertSame($elements, $client->query(self::QUERY));
    }

    public function testOfflineModeNeverTouchesTheNetwork(): void
    {
        // The endpoint is unusable on purpose: reaching for it would fail differently
        $client = $this->clientFor(new Settings(
            overpassEndpoints: ['http://127.0.0.1:1/unreachable'],
            overpassCacheDir: $this->directory,
            overpassOffline: true,
        ));

        $this->expectException(OverpassUnavailableException::class);
        $this->expectExceptionMessage('offline mode');

        $client->query(self::QUERY);
    }

    private function clientFor(Settings $settings): OverpassClient
    {
        $client = AppFactory::create($settings)->getContainer()->get(OverpassClient::class);
        self::assertInstanceOf(OverpassClient::class, $client);

        return $client;
    }
}
