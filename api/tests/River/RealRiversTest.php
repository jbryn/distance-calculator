<?php

declare(strict_types=1);

namespace Jbryn\Downriver\Tests\River;

use Jbryn\Downriver\Geo\Point;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Stitches real rivers saved from OpenStreetMap and compares distances along them
 * with official figures from IMGW-PIB, the Polish hydrological service.
 *
 * See tests/fixtures/README.md for where the data comes from.
 */
class RealRiversTest extends TestCase
{
    private const REFERENCE_FILE = __DIR__ . '/../fixtures/imgw-reference-distances.json';

    /**
     * Stretches where OpenStreetMap and IMGW differ by more than the reference file
     * allows, with the widest difference accepted here and the likely reason.
     */
    private const KNOWN_DEVIATIONS = [
        // The Bielinek gauge lies 630 m from the river's line, so it snaps to the wrong
        // spot. The two errors cancel: Gozdowice to Widuchowa is 56.81 km against 56.76.
        'odra:Gozdowice->Bielinek' => 0.04,
        'odra:Bielinek->Widuchowa' => 0.04,
        // 34.09 km against 32.87 on a strongly meandering stretch. Probably the
        // meanders are drawn in more detail than the old official measurement.
        'biebrza:Dębowo->Osowiec' => 0.04,
    ];

    #[DataProvider('rivers')]
    public function testStitchesTheSavedRiver(
        string $river,
        float $kilometers,
        int $pieces,
        int $piecesInLine,
        int $leftovers,
        int $gaps,
    ): void {
        $id = RiverFixtures::RELATIONS[$river];
        $stitched = RiverFixtures::stitched($id);

        $this->assertCount($pieces, RiverFixtures::relation($id)->pieces);
        $this->assertCount($piecesInLine, $stitched->wayIds);
        $this->assertCount($leftovers, $stitched->leftovers);
        $this->assertCount($gaps, $stitched->gaps());
        $this->assertEqualsWithDelta($kilometers, $stitched->line->length() / 1000, 0.01);
    }

    /**
     * Length of the line in km, main-course pieces in the relation, pieces used for the
     * line, leftover parts, and how many of those are gaps.
     *
     * @return array<string, array{string, float, int, int, int, int}>
     */
    public static function rivers(): array
    {
        return [
            'Biała: no roles on any piece' => ['Biała', 107.80, 8, 8, 0, 0],
            'Biebrza' => ['Biebrza', 176.28, 49, 49, 0, 0],
            'Bóbr: three branches' => ['Bóbr', 280.79, 87, 82, 3, 0],
            'Dunajec: a branch joining mid-piece' => ['Dunajec', 199.90, 25, 24, 1, 0],
            'Gwda' => ['Gwda', 142.93, 15, 15, 0, 0],
            'Noteć: five branches' => ['Noteć', 385.16, 122, 117, 5, 0],
            'Odra' => ['Odra', 853.34, 256, 256, 0, 0],
            'Pasłęka' => ['Pasłęka', 197.25, 17, 17, 0, 0],
            'Raba' => ['Raba', 133.00, 36, 35, 1, 0],
            'Rega: mostly without roles, splits and rejoins' => ['Rega', 181.89, 24, 20, 2, 0],
            'Soła: cut into four parts' => ['Soła', 39.83, 13, 7, 3, 3],
            'Warta' => ['Warta', 801.10, 245, 245, 0, 0],
            'Wieprz' => ['Wieprz', 373.27, 53, 53, 0, 0],
            'Wisła' => ['Wisła', 1011.34, 106, 106, 0, 0],
            'Wisłok' => ['Wisłok', 217.79, 17, 17, 0, 0],
            'Wisłoka' => ['Wisłoka', 177.07, 7, 7, 0, 0],
        ];
    }

    public function testEverySavedRiverIsInTheTableAbove(): void
    {
        $this->assertEqualsCanonicalizing(
            array_keys(RiverFixtures::RELATIONS),
            array_column(self::rivers(), 0),
        );
    }

    public function testGwdaRunsFromItsSourceToTheNotec(): void
    {
        $points = RiverFixtures::stitched(RiverFixtures::RELATIONS['Gwda'])->line->points();
        $mouth = new Point(53.0551, 16.7260);

        // The source is near Biały Bór, 90 km to the north
        $this->assertGreaterThan(53.8, $points[0]->latitude);
        $this->assertLessThan(50, $points[count($points) - 1]->distanceTo($mouth));
    }

    public function testSolaIsReportedAsBrokenNotMeasuredAcrossItsGaps(): void
    {
        $sola = RiverFixtures::stitched(RiverFixtures::RELATIONS['Soła']);

        $this->assertFalse($sola->isContinuous());

        $lengths = array_map(static fn($gap): float => round($gap->length / 1000, 1), $sola->gaps());
        sort($lengths);

        // The line covers 39.8 km; another 43.7 km of the river is not connected to it
        $this->assertSame([7.5, 13.8, 22.4], $lengths);
    }

    #[DataProvider('referenceStretches')]
    public function testDistanceBetweenGaugingStationsMatchesImgw(
        string $river,
        Point $from,
        Point $to,
        float $expectedKilometers,
        float $tolerance,
    ): void {
        $line = RiverFixtures::stitched(RiverFixtures::RELATIONS[$river])->line;

        $start = $line->locate($from);
        $end = $line->locate($to);

        // Positive only when the line runs downstream: IMGW pairs go from the upper station to the lower
        $kilometers = ($end->distance - $start->distance) / 1000;

        $this->assertEqualsWithDelta(
            $expectedKilometers,
            $kilometers,
            $expectedKilometers * $tolerance,
            sprintf(
                'IMGW says %.2f km, the line gives %.2f km (stations snapped %.0f m and %.0f m to the line)',
                $expectedKilometers,
                $kilometers,
                $start->offset,
                $end->offset,
            ),
        );
    }

    /**
     * Every pair in the reference file whose river is saved as a fixture.
     *
     * @return array<string, array{string, Point, Point, float, float}>
     */
    public static function referenceStretches(): array
    {
        $reference = self::reference();

        $stations = [];
        foreach ($reference['points'] as $station) {
            $stations[$station['id']] = new Point($station['lat'], $station['lon']);
        }

        $stretches = [];
        foreach ($reference['pairs'] as $pair) {
            if (!isset(RiverFixtures::RELATIONS[$pair['river']])) {
                continue;
            }

            $stretches[$pair['id']] = [
                $pair['river'],
                $stations[$pair['from']],
                $stations[$pair['to']],
                $pair['expectedKm'],
                self::KNOWN_DEVIATIONS[$pair['id']] ?? $pair['relTolerance'],
            ];
        }

        return $stretches;
    }

    public function testTheReferenceFileCoversTheSavedRivers(): void
    {
        $stretches = self::referenceStretches();

        // Guards against the comparison silently shrinking, for example after a river is renamed
        $this->assertCount(33, $stretches);
        $this->assertEqualsCanonicalizing(
            array_keys(RiverFixtures::RELATIONS),
            array_values(array_unique(array_column($stretches, 0))),
        );

        foreach (array_keys(self::KNOWN_DEVIATIONS) as $id) {
            $this->assertArrayHasKey($id, $stretches);
        }
    }

    /**
     * @return array{
     *     points: list<array{id: string, lat: float, lon: float}>,
     *     pairs: list<array{id: string, river: string, from: string, to: string, expectedKm: float, relTolerance: float}>
     * }
     */
    private static function reference(): array
    {
        $contents = file_get_contents(self::REFERENCE_FILE);

        if ($contents === false) {
            throw new RuntimeException('Could not read ' . self::REFERENCE_FILE);
        }

        /** @var array{points: list<array{id: string, lat: float, lon: float}>, pairs: list<array{id: string, river: string, from: string, to: string, expectedKm: float, relTolerance: float}>} $reference */
        $reference = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

        return $reference;
    }
}
