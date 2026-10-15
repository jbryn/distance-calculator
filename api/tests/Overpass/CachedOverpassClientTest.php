<?php

declare(strict_types=1);

namespace Jbryn\Downriver\Tests\Overpass;

use Jbryn\Downriver\Overpass\CachedOverpassClient;
use Jbryn\Downriver\Overpass\OfflineOverpassClient;
use Jbryn\Downriver\Overpass\OverpassUnavailableException;
use Jbryn\Downriver\Overpass\QueryCache;
use PHPUnit\Framework\TestCase;

class CachedOverpassClientTest extends TestCase
{
    use TemporaryDirectory;

    private const QUERY = '[out:json];relation(8974372);out geom;';
    private const OLD = [['type' => 'relation', 'id' => 1, 'tags' => ['name' => 'old']]];
    private const NEW = [['type' => 'relation', 'id' => 1, 'tags' => ['name' => 'new']]];
    private const ONE_DAY = 86_400;

    private FakeOverpassClient $source;

    /** The current time as the code under test sees it */
    private int $now = 1_700_000_000;

    protected function setUp(): void
    {
        $this->createTemporaryDirectory();
        $this->source = new FakeOverpassClient(self::NEW);
    }

    protected function tearDown(): void
    {
        $this->removeTemporaryDirectory();
    }

    public function testFirstQueryGoesToTheSourceAndIsSaved(): void
    {
        $elements = $this->client()->query(self::QUERY);

        $this->assertSame(self::NEW, $elements);
        $this->assertSame([self::QUERY], $this->source->queries);
        $this->assertSame(self::NEW, $this->cache()->get(self::QUERY)?->elements);
    }

    public function testRepeatedQueryIsAnsweredWithoutTheSource(): void
    {
        $client = $this->client();

        $client->query(self::QUERY);
        $this->now += self::ONE_DAY - 1;
        $elements = $client->query(self::QUERY);

        $this->assertSame(self::NEW, $elements);
        $this->assertCount(1, $this->source->queries);
    }

    public function testExpiredEntryIsRefreshed(): void
    {
        $this->source->elements = self::OLD;
        $client = $this->client();
        $client->query(self::QUERY);

        $this->now += self::ONE_DAY;
        $this->source->elements = self::NEW;
        $elements = $client->query(self::QUERY);

        $this->assertSame(self::NEW, $elements);
        $this->assertCount(2, $this->source->queries);
        $saved = $this->cache()->get(self::QUERY);
        $this->assertNotNull($saved);
        $this->assertSame(self::NEW, $saved->elements);
        $this->assertSame($this->now, $saved->fetchedAt);
    }

    public function testExpiredEntryIsStillServedWhenTheRefreshFails(): void
    {
        $this->source->elements = self::OLD;
        $client = $this->client();
        $client->query(self::QUERY);

        $this->now += 100 * self::ONE_DAY;
        $this->source->available = false;
        $elements = $client->query(self::QUERY);

        $this->assertSame(self::OLD, $elements);
        $this->assertCount(2, $this->source->queries);
    }

    public function testFailsWhenTheSourceFailsAndNothingIsSaved(): void
    {
        $this->source->available = false;

        $this->expectException(OverpassUnavailableException::class);
        $this->expectExceptionMessage('fake outage');

        $this->client()->query(self::QUERY);
    }

    public function testAFailureIsNotSaved(): void
    {
        $this->source->available = false;

        try {
            $this->client()->query(self::QUERY);
        } catch (OverpassUnavailableException) {
        }

        $this->assertNull($this->cache()->get(self::QUERY));
    }

    public function testAnEmptyResultIsSavedToo(): void
    {
        $this->source->elements = [];
        $client = $this->client();

        $client->query(self::QUERY);
        $client->query(self::QUERY);

        $this->assertCount(1, $this->source->queries);
    }

    public function testOfflineModeServesSavedResponsesHoweverOld(): void
    {
        $this->cache()->put(self::QUERY, self::OLD);
        $this->now += 1000 * self::ONE_DAY;

        $offline = new CachedOverpassClient(new OfflineOverpassClient(), $this->cache(), self::ONE_DAY, $this->clock());

        $this->assertSame(self::OLD, $offline->query(self::QUERY));
    }

    public function testOfflineModeFailsForAQueryThatIsNotSaved(): void
    {
        $offline = new CachedOverpassClient(new OfflineOverpassClient(), $this->cache(), self::ONE_DAY, $this->clock());

        $this->expectException(OverpassUnavailableException::class);
        $this->expectExceptionMessage('offline mode');

        $offline->query(self::QUERY);
    }

    private function client(): CachedOverpassClient
    {
        return new CachedOverpassClient($this->source, $this->cache(), self::ONE_DAY, $this->clock());
    }

    private function cache(): QueryCache
    {
        return new QueryCache($this->directory, $this->clock());
    }

    private function clock(): \Closure
    {
        return fn(): int => $this->now;
    }
}
