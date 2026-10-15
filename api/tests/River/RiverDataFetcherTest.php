<?php

declare(strict_types=1);

namespace Jbryn\Downriver\Tests\River;

use Jbryn\Downriver\Overpass\OverpassClient;
use Jbryn\Downriver\Overpass\OverpassUnavailableException;
use Jbryn\Downriver\Overpass\QueryCache;
use Jbryn\Downriver\River\MapFeature;
use Jbryn\Downriver\River\RiverDataFetcher;
use Jbryn\Downriver\River\RiverRelation;
use Jbryn\Downriver\Tests\Overpass\TemporaryDirectory;
use PHPUnit\Framework\TestCase;

class RiverDataFetcherTest extends TestCase
{
    use TemporaryDirectory;

    private const GWDA = 8974372;

    private QueryCache $cache;

    /** @var list<int> seconds the fetcher asked to wait */
    private array $pauses = [];

    protected function setUp(): void
    {
        $this->createTemporaryDirectory();
        $this->cache = new QueryCache($this->directory, static fn(): int => 1_760_000_000);
    }

    protected function tearDown(): void
    {
        $this->removeTemporaryDirectory();
    }

    public function testARiverIsDescribedByItsCourseAndItsFeatures(): void
    {
        $this->assertSame(
            [RiverRelation::overpassQuery(self::GWDA), MapFeature::overpassQuery(self::GWDA)],
            RiverDataFetcher::queries(self::GWDA),
        );
    }

    public function testSavesTheAnswerToEveryQuery(): void
    {
        $source = $this->source(['course'], ['features']);

        $this->fetcher($source)->fetch(self::GWDA);

        $this->assertSame(RiverDataFetcher::queries(self::GWDA), $source->queries);
        $this->assertSame([['is' => 'course']], $this->saved(RiverRelation::overpassQuery(self::GWDA)));
        $this->assertSame([['is' => 'features']], $this->saved(MapFeature::overpassQuery(self::GWDA)));
        $this->assertSame([], $this->pauses);
    }

    public function testAsksAgainWhenTheServerRefuses(): void
    {
        $source = $this->source(null, null, ['course'], null, ['features']);

        $this->fetcher($source)->fetch(self::GWDA);

        $this->assertSame([['is' => 'course']], $this->saved(RiverRelation::overpassQuery(self::GWDA)));
        $this->assertSame([['is' => 'features']], $this->saved(MapFeature::overpassQuery(self::GWDA)));
    }

    public function testWaitsLongerBeforeEachNewAttempt(): void
    {
        $this->fetcher($this->source(null, null, null, ['course'], ['features']))->fetch(self::GWDA);

        $this->assertSame([15, 30, 45], $this->pauses);
    }

    public function testGivesUpAfterTheLastAttempt(): void
    {
        $source = $this->source(null, null, null);

        try {
            $this->fetcher($source, attempts: 3)->fetch(self::GWDA);
            $this->fail('Expected an OverpassUnavailableException');
        } catch (OverpassUnavailableException $e) {
            $this->assertSame('refused', $e->getMessage());
        }

        $this->assertCount(3, $source->queries);
        $this->assertSame([15, 30], $this->pauses);
    }

    public function testSavesNothingWhenOnlyPartOfTheRiverCouldBeFetched(): void
    {
        // The course arrives, the features never do
        $source = $this->source(['course'], null, null);

        try {
            $this->fetcher($source, attempts: 2)->fetch(self::GWDA);
            $this->fail('Expected an OverpassUnavailableException');
        } catch (OverpassUnavailableException) {
            $this->assertNull($this->cache->get(RiverRelation::overpassQuery(self::GWDA)));
            $this->assertNull($this->cache->get(MapFeature::overpassQuery(self::GWDA)));
        }
    }

    public function testAFailedRefreshLeavesTheEarlierDataInPlace(): void
    {
        $this->cache->put(RiverRelation::overpassQuery(self::GWDA), [['is' => 'old course']]);

        try {
            $this->fetcher($this->source(['course'], null), attempts: 1)->fetch(self::GWDA);
            $this->fail('Expected an OverpassUnavailableException');
        } catch (OverpassUnavailableException) {
            $this->assertSame([['is' => 'old course']], $this->saved(RiverRelation::overpassQuery(self::GWDA)));
        }
    }

    private function fetcher(OverpassClient $source, int $attempts = 5): RiverDataFetcher
    {
        return new RiverDataFetcher($source, $this->cache, $attempts, function (int $seconds): void {
            $this->pauses[] = $seconds;
        });
    }

    /**
     * A client that gives one prepared answer per call: a marker element, or null to refuse.
     *
     * @param list<string>|null ...$answers
     */
    private function source(?array ...$answers): ScriptedOverpassClient
    {
        return new ScriptedOverpassClient(array_values($answers));
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    private function saved(string $query): ?array
    {
        return $this->cache->get($query)?->elements;
    }
}
