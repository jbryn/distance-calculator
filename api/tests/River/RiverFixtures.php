<?php

declare(strict_types=1);

namespace Jbryn\Downriver\Tests\River;

use Jbryn\Downriver\Overpass\OverpassClient;
use Jbryn\Downriver\Overpass\OverpassUnavailableException;
use Jbryn\Downriver\Overpass\QueryCache;
use Jbryn\Downriver\River\RiverRelation;
use Jbryn\Downriver\River\RiverStitcher;
use Jbryn\Downriver\River\StitchedRiver;

/**
 * Real rivers saved in tests/fixtures/overpass, read the way the application reads them offline.
 */
final class RiverFixtures
{
    /** OpenStreetMap relation ids, by the river name the IMGW reference file uses */
    public const RELATIONS = [
        'Biała' => 19995496,
        'Biebrza' => 6378830,
        'Bóbr' => 1582509,
        'Dunajec' => 2822415,
        'Gwda' => 8974372,
        'Noteć' => 6124894,
        'Odra' => 387605,
        'Pasłęka' => 7581082,
        'Raba' => 2473086,
        'Rega' => 12759496,
        'Soła' => 545358,
        'Warta' => 3272339,
        'Wieprz' => 2297716,
        'Wisła' => 34392,
        'Wisłok' => 2020696,
        'Wisłoka' => 7577712,
    ];

    /** Rivers the application ships with */
    public const SHIPPED = __DIR__ . '/../../data/overpass';

    /** Rivers kept only as test cases, such as the Soła with its gaps */
    public const TEST_ONLY = __DIR__ . '/../fixtures/overpass';

    /** @var array<int, StitchedRiver> */
    private static array $stitched = [];

    public static function relation(int $id): RiverRelation
    {
        return RiverRelation::fromElement(self::client()->query(RiverRelation::overpassQuery($id))[0]);
    }

    /**
     * Answers from the shipped rivers first, then from the test-only ones, and never from the network.
     */
    public static function client(): OverpassClient
    {
        return self::savedResponses([self::SHIPPED, self::TEST_ONLY]);
    }

    /**
     * Answers only from the rivers the application ships with.
     */
    public static function shippedOnlyClient(): OverpassClient
    {
        return self::savedResponses([self::SHIPPED]);
    }

    /**
     * @param list<string> $directories
     */
    private static function savedResponses(array $directories): OverpassClient
    {
        return new class ($directories) implements OverpassClient {
            /**
             * @param list<string> $directories
             */
            public function __construct(private readonly array $directories) {}

            public function query(string $query): array
            {
                foreach ($this->directories as $directory) {
                    $saved = (new QueryCache($directory))->get($query);

                    if ($saved !== null) {
                        return $saved->elements;
                    }
                }

                throw new OverpassUnavailableException('no saved response for this query');
            }
        };
    }

    public static function stitched(int $id): StitchedRiver
    {
        return self::$stitched[$id] ??= (new RiverStitcher())->stitch(self::relation($id)->pieces);
    }
}
