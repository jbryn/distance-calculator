<?php

declare(strict_types=1);

namespace Jbryn\DistanceCalculator\Tests\Geo;

use Jbryn\DistanceCalculator\Geo\LinePosition;
use Jbryn\DistanceCalculator\Geo\Point;
use Jbryn\DistanceCalculator\Geo\Polyline;
use Jbryn\DistanceCalculator\Tests\River\RiverFixtures;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Polyline::locate() skips parts of the line it can prove are too far away. These tests
 * check that it still finds exactly what a search of every segment would find.
 */
class PolylineSnappingTest extends TestCase
{
    /** Length of one degree of arc: 111,194.93 m */
    private const DEGREE = M_PI / 180 * Point::EARTH_RADIUS_METERS;

    #[DataProvider('realRivers')]
    public function testFindsTheSameNearestPositionAsCheckingEverySegment(string $river): void
    {
        $line = RiverFixtures::stitched(RiverFixtures::RELATIONS[$river])->line;
        $segments = self::segments($line);

        foreach (self::pointsAround($line, 42) as $point) {
            self::assertSamePosition(self::searchEverySegment($line, $segments, $point), $line->locate($point));
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function realRivers(): array
    {
        // The Odra runs mostly north, the Noteć mostly west; both double back on themselves often
        return ['Odra' => ['Odra'], 'Noteć' => ['Noteć']];
    }

    #[DataProvider('segmentCounts')]
    public function testChunkBoundariesDoNotChangeTheResult(int $count): void
    {
        // A zigzag along the equator, one segment per 0.01 degrees of longitude
        $points = [];
        for ($i = 0; $i <= $count; $i++) {
            $points[] = new Point($i % 2 === 0 ? 0.0 : 0.005, $i * 0.01);
        }
        $line = new Polyline($points);
        $segments = self::segments($line);

        foreach (self::pointsAround($line, $count) as $point) {
            self::assertSamePosition(self::searchEverySegment($line, $segments, $point), $line->locate($point));
        }
    }

    /**
     * @return array<string, array{int}>
     */
    public static function segmentCounts(): array
    {
        return [
            'one segment' => [1],
            'one short of a full chunk' => [31],
            'exactly one chunk' => [32],
            'one segment into a second chunk' => [33],
            'many chunks' => [500],
        ];
    }

    public function testFindsAChunkNearThePointAcrossTheAntimeridian(): void
    {
        // 200 segments heading west towards the antimeridian, ending just short of it
        $points = [];
        for ($i = 0; $i <= 200; $i++) {
            $points[] = new Point(0.0, -178.0 - $i * 0.0099);
        }
        $line = new Polyline($points);

        // Just across the antimeridian from the end of the line: 0.005 + 0.0200 degrees away
        $position = $line->locate(new Point(0.0, 179.995));

        $this->assertEqualsWithDelta($line->length(), $position->distance, 0.01);
        $this->assertEqualsWithDelta(0.025 * self::DEGREE, $position->offset, 0.01);
    }

    public function testALongLineAcrossTheAntimeridian(): void
    {
        // 200 segments from longitude 179 to -179, crossing 180 in the middle
        $points = [];
        for ($i = 0; $i <= 200; $i++) {
            $longitude = 179.0 + $i * 0.01;
            $points[] = new Point(0.0, $longitude > 180.0 ? $longitude - 360.0 : $longitude);
        }
        $line = new Polyline($points);
        $segments = self::segments($line);

        foreach ([new Point(0.1, 179.995), new Point(-0.1, -179.995), new Point(0.0, -179.5), new Point(0.0, 178.0)] as $point) {
            self::assertSamePosition(self::searchEverySegment($line, $segments, $point), $line->locate($point));
        }

        $this->assertEqualsWithDelta(1.005 * self::DEGREE, $line->locate(new Point(0.1, -179.995))->distance, 0.01);
    }

    /**
     * Every segment of the line as a line of its own. A one-segment line has one chunk,
     * which is always searched, so locating on it is a plain calculation.
     *
     * @return list<Polyline>
     */
    private static function segments(Polyline $line): array
    {
        $points = $line->points();
        $segments = [];

        for ($i = 0, $last = count($points) - 1; $i < $last; $i++) {
            $segments[] = new Polyline([$points[$i], $points[$i + 1]]);
        }

        return $segments;
    }

    /**
     * The reference: try every segment and keep the nearest.
     *
     * @param list<Polyline> $segments
     */
    private static function searchEverySegment(Polyline $line, array $segments, Point $point): LinePosition
    {
        $best = null;
        $start = 0.0;

        foreach ($segments as $segment) {
            $position = $segment->locate($point);

            if ($best === null || $position->offset < $best->offset) {
                $best = new LinePosition($start + $position->distance, $position->point, $position->offset);
            }

            $start += $segment->length();
        }

        self::assertNotNull($best);

        return $best;
    }

    /**
     * Points scattered over the area around the line, and points just beside it.
     *
     * @return list<Point>
     */
    private static function pointsAround(Polyline $line, int $seed): array
    {
        mt_srand($seed);

        $points = $line->points();
        $latitudes = array_map(static fn(Point $point): float => $point->latitude, $points);
        $longitudes = array_map(static fn(Point $point): float => $point->longitude, $points);

        $random = static fn(float $from, float $to): float => $from + ($to - $from) * mt_rand() / mt_getrandmax();

        $around = [];

        for ($i = 0; $i < 10; $i++) {
            $around[] = new Point(
                $random(min($latitudes) - 0.2, max($latitudes) + 0.2),
                $random(min($longitudes) - 0.2, max($longitudes) + 0.2),
            );
        }

        for ($i = 0; $i < 10; $i++) {
            $near = $points[mt_rand(0, count($points) - 1)];
            $around[] = new Point($near->latitude + $random(-0.01, 0.01), $near->longitude + $random(-0.01, 0.01));
        }

        return $around;
    }

    private static function assertSamePosition(LinePosition $expected, LinePosition $actual): void
    {
        self::assertEqualsWithDelta($expected->distance, $actual->distance, 1e-6);
        self::assertEqualsWithDelta($expected->offset, $actual->offset, 1e-9);
        self::assertEqualsWithDelta($expected->point->latitude, $actual->point->latitude, 1e-12);
        self::assertEqualsWithDelta($expected->point->longitude, $actual->point->longitude, 1e-12);
    }
}
