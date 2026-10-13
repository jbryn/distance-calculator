<?php

declare(strict_types=1);

namespace Jbryn\DistanceCalculator\Tests\River;

use Jbryn\DistanceCalculator\Overpass\CachedOverpassClient;
use Jbryn\DistanceCalculator\Overpass\OfflineOverpassClient;
use Jbryn\DistanceCalculator\Overpass\QueryCache;
use Jbryn\DistanceCalculator\River\RiverRelation;
use Jbryn\DistanceCalculator\River\RiverStitcher;
use Jbryn\DistanceCalculator\River\StitchedRiver;

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

    private const DIRECTORY = __DIR__ . '/../fixtures/overpass';

    /** @var array<int, StitchedRiver> */
    private static array $stitched = [];

    public static function relation(int $id): RiverRelation
    {
        $client = new CachedOverpassClient(new OfflineOverpassClient(), new QueryCache(self::DIRECTORY), PHP_INT_MAX);

        return RiverRelation::fromElement($client->query(RiverRelation::overpassQuery($id))[0]);
    }

    public static function stitched(int $id): StitchedRiver
    {
        return self::$stitched[$id] ??= (new RiverStitcher())->stitch(self::relation($id)->pieces);
    }
}
