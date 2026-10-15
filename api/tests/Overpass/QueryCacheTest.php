<?php

declare(strict_types=1);

namespace Jbryn\Downriver\Tests\Overpass;

use Jbryn\Downriver\Overpass\QueryCache;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class QueryCacheTest extends TestCase
{
    use TemporaryDirectory;

    private const QUERY = '[out:json];relation(8974372);out geom;';
    private const ELEMENTS = [['type' => 'relation', 'id' => 8974372, 'tags' => ['name' => 'Głomia']]];

    protected function setUp(): void
    {
        $this->createTemporaryDirectory();
    }

    protected function tearDown(): void
    {
        $this->removeTemporaryDirectory();
    }

    public function testReturnsNothingForAQueryThatWasNeverSaved(): void
    {
        $this->assertNull($this->cache()->get(self::QUERY));
    }

    public function testReturnsWhatWasSavedWithItsFetchTime(): void
    {
        $cache = $this->cache(now: 1_700_000_000);

        $cache->put(self::QUERY, self::ELEMENTS);
        $entry = $cache->get(self::QUERY);

        $this->assertNotNull($entry);
        $this->assertSame(self::ELEMENTS, $entry->elements);
        $this->assertSame(1_700_000_000, $entry->fetchedAt);
    }

    public function testSurvivesANewCacheInstance(): void
    {
        $this->cache()->put(self::QUERY, self::ELEMENTS);

        $this->assertSame(self::ELEMENTS, $this->cache()->get(self::QUERY)?->elements);
    }

    public function testCreatesTheDirectoryWhenMissing(): void
    {
        $this->assertDirectoryDoesNotExist($this->directory);

        $this->cache()->put(self::QUERY, self::ELEMENTS);

        $this->assertDirectoryExists($this->directory);
    }

    public function testKeepsDifferentQueriesApart(): void
    {
        $cache = $this->cache();
        $other = '[out:json];relation(1);out geom;';

        $cache->put(self::QUERY, self::ELEMENTS);
        $cache->put($other, []);

        $this->assertSame(self::ELEMENTS, $cache->get(self::QUERY)?->elements);
        $this->assertSame([], $cache->get($other)?->elements);
    }

    public function testTreatsQueriesDifferingOnlyInWhitespaceAsTheSame(): void
    {
        $cache = $this->cache();

        $cache->put('[out:json]; relation(8974372); out geom;', self::ELEMENTS);

        $this->assertNotNull($cache->get("  [out:json];\n    relation(8974372);\n    out   geom;\n"));
    }

    public function testOverwritesAnEarlierEntry(): void
    {
        $cache = $this->cache();

        $cache->put(self::QUERY, []);
        $cache->put(self::QUERY, self::ELEMENTS);

        $this->assertSame(self::ELEMENTS, $cache->get(self::QUERY)?->elements);
        $this->assertCount(1, glob($this->directory . '/*') ?: []);
    }

    public function testSavedFileIsReadableAndRecordsTheQuery(): void
    {
        $cache = $this->cache(now: 1_700_000_000);

        $cache->put(self::QUERY, self::ELEMENTS);

        $saved = json_decode((string) file_get_contents($cache->path(self::QUERY)), true);
        $this->assertSame(
            ['query' => self::QUERY, 'fetchedAt' => 1_700_000_000, 'elements' => self::ELEMENTS],
            $saved,
        );
        $this->assertStringContainsString('Głomia', (string) file_get_contents($cache->path(self::QUERY)));
    }

    /**
     * @return array<array<mixed>>
     */
    public static function damagedFiles(): array
    {
        return [
            'not JSON' => ['{"elements": ['],
            'empty' => [''],
            'JSON of the wrong shape' => ['{"elements": "nope", "fetchedAt": 1}'],
            'missing fetch time' => ['{"elements": []}'],
            'element that is not an object' => ['{"elements": [1], "fetchedAt": 1}'],
        ];
    }

    #[DataProvider('damagedFiles')]
    public function testTreatsADamagedFileAsNotSaved(string $contents): void
    {
        $cache = $this->cache();
        $cache->put(self::QUERY, self::ELEMENTS);
        file_put_contents($cache->path(self::QUERY), $contents);

        $this->assertNull($cache->get(self::QUERY));
    }

    private function cache(?int $now = null): QueryCache
    {
        return new QueryCache($this->directory, $now === null ? null : static fn(): int => $now);
    }
}
