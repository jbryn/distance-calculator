<?php

declare(strict_types=1);

namespace Jbryn\DistanceCalculator\Tests\Geo;

use InvalidArgumentException;
use Jbryn\DistanceCalculator\Geo\Point;
use Jbryn\DistanceCalculator\Geo\Polyline;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Most tests use lines along the equator or a meridian, where one degree is exactly
 * DEGREE meters and positions can be worked out by hand.
 */
class PolylineTest extends TestCase
{
    /** Length of one degree of arc: 111,194.93 m */
    private const DEGREE = M_PI / 180 * Point::EARTH_RADIUS_METERS;

    /** Tolerance in meters for positions and lengths */
    private const DELTA = 0.01;

    public function testNeedsAtLeastTwoPoints(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A polyline needs at least two points');

        new Polyline([new Point(0, 0)]);
    }

    public function testLengthOfAStraightLine(): void
    {
        $this->assertEqualsWithDelta(2 * self::DEGREE, $this->eastwardLine()->length(), self::DELTA);
    }

    public function testLengthFollowsTheBends(): void
    {
        $line = $this->rightAngle();
        $straight = (new Point(0, 0))->distanceTo(new Point(1, 1));

        $this->assertEqualsWithDelta(2 * self::DEGREE, $line->length(), self::DELTA);
        $this->assertGreaterThan($straight, $line->length());
    }

    public function testLengthIgnoresRepeatedPoints(): void
    {
        $line = new Polyline([new Point(0, 0), new Point(0, 1), new Point(0, 1), new Point(0, 2)]);

        $this->assertEqualsWithDelta(2 * self::DEGREE, $line->length(), self::DELTA);
    }

    // --- locate ---------------------------------------------------------------------------

    public function testLocatesAPointLyingOnAVertex(): void
    {
        $position = $this->eastwardLine()->locate(new Point(0, 1));

        $this->assertEqualsWithDelta(self::DEGREE, $position->distance, self::DELTA);
        $this->assertEqualsWithDelta(0.0, $position->offset, self::DELTA);
        $this->assertPointEquals(new Point(0, 1), $position->point);
    }

    public function testLocatesAPointLyingMidSegment(): void
    {
        $position = $this->eastwardLine()->locate(new Point(0, 1.5));

        $this->assertEqualsWithDelta(1.5 * self::DEGREE, $position->distance, self::DELTA);
        $this->assertEqualsWithDelta(0.0, $position->offset, self::DELTA);
    }

    public function testSnapsAPointBesideTheLine(): void
    {
        $position = $this->eastwardLine()->locate(new Point(0.1, 0.5));

        $this->assertEqualsWithDelta(0.5 * self::DEGREE, $position->distance, self::DELTA);
        $this->assertEqualsWithDelta(0.1 * self::DEGREE, $position->offset, self::DELTA);
        $this->assertPointEquals(new Point(0, 0.5), $position->point);
    }

    public function testSnapsAPointBeforeTheStartToTheFirstPoint(): void
    {
        $position = $this->eastwardLine()->locate(new Point(0, -1));

        $this->assertSame(0.0, $position->distance);
        $this->assertEqualsWithDelta(self::DEGREE, $position->offset, self::DELTA);
        $this->assertPointEquals(new Point(0, 0), $position->point);
    }

    public function testSnapsAPointPastTheEndToTheLastPoint(): void
    {
        $line = $this->eastwardLine();
        $position = $line->locate(new Point(0, 3));

        $this->assertSame($line->length(), $position->distance);
        $this->assertEqualsWithDelta(self::DEGREE, $position->offset, self::DELTA);
        $this->assertPointEquals(new Point(0, 2), $position->point);
    }

    public function testSnapsToTheNearerLegOfABend(): void
    {
        // Closer to the northward second leg (0.2° away) than to the first (0.5° away)
        $position = $this->rightAngle()->locate(new Point(0.5, 1.2));

        $this->assertEqualsWithDelta(1.5 * self::DEGREE, $position->distance, self::DELTA);
        $this->assertPointEquals(new Point(0.5, 1), $position->point);
    }

    public function testSnapsToTheNearerBankOfAHairpin(): void
    {
        // Out along the equator, back along a parallel 0.01° to the north
        $hairpin = new Polyline([new Point(0, 0), new Point(0, 1), new Point(0.01, 1), new Point(0.01, 0)]);

        $outward = $hairpin->locate(new Point(0.002, 0.5));
        $return = $hairpin->locate(new Point(0.008, 0.5));

        $this->assertEqualsWithDelta(0.5 * self::DEGREE, $outward->distance, 1.0);
        $this->assertEqualsWithDelta(1.51 * self::DEGREE, $return->distance, 1.0);
    }

    public function testSnappingAccountsForLongitudeShrinkingWithLatitude(): void
    {
        // At 60°N a degree of longitude covers half the ground of a degree of latitude, so this
        // segment (0.1° east, 0.05° north) runs at 45° on the ground. The point sits square-on
        // to its midpoint; treating degrees as equal in both directions would land ~300 m off.
        $line = new Polyline([new Point(60, 10), new Point(60.05, 10.1)]);

        $position = $line->locate(new Point(60.02, 10.06));

        $this->assertEqualsWithDelta($line->length() / 2, $position->distance, 25.0);
    }

    public function testLocatesAcrossTheAntimeridian(): void
    {
        $line = new Polyline([new Point(0, 179), new Point(0, -179)]);

        $position = $line->locate(new Point(0.1, -179.5));

        $this->assertEqualsWithDelta(2 * self::DEGREE, $line->length(), self::DELTA);
        $this->assertEqualsWithDelta(1.5 * self::DEGREE, $position->distance, self::DELTA);
        $this->assertPointEquals(new Point(0, -179.5), $position->point);
    }

    // --- pointAt --------------------------------------------------------------------------

    public function testPointAtTheEndsAndInBetween(): void
    {
        $line = $this->rightAngle();

        $this->assertPointEquals(new Point(0, 0), $line->pointAt(0.0));
        $this->assertPointEquals(new Point(0, 0.25), $line->pointAt(0.25 * self::DEGREE));
        $this->assertPointEquals(new Point(0, 1), $line->pointAt(self::DEGREE));
        $this->assertPointEquals(new Point(0.75, 1), $line->pointAt(1.75 * self::DEGREE));
        $this->assertPointEquals(new Point(1, 1), $line->pointAt($line->length()));
    }

    public function testPointAtIsTheInverseOfLocate(): void
    {
        $line = $this->rightAngle();

        foreach ([0.0, 12345.6, self::DEGREE, 150000.0, $line->length()] as $distance) {
            $this->assertEqualsWithDelta($distance, $line->locate($line->pointAt($distance))->distance, self::DELTA);
        }
    }

    #[DataProvider('distancesOffTheLine')]
    public function testPointAtRejectsDistancesOffTheLine(float $distance): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is outside the line');

        $this->eastwardLine()->pointAt($distance);
    }

    /**
     * @return array<array<mixed>>
     */
    public static function distancesOffTheLine(): array
    {
        return [
            'negative' => [-0.01],
            'past the end' => [2 * self::DEGREE + 1],
            'not a number' => [NAN],
        ];
    }

    // --- section --------------------------------------------------------------------------

    public function testSectionWithinOneSegment(): void
    {
        $section = $this->eastwardLine()->section(0.25 * self::DEGREE, 0.75 * self::DEGREE);

        $this->assertEqualsWithDelta(0.5 * self::DEGREE, $section->length(), self::DELTA);
        $this->assertCount(2, $section->points());
        $this->assertPointEquals(new Point(0, 0.25), $section->points()[0]);
        $this->assertPointEquals(new Point(0, 0.75), $section->points()[1]);
    }

    public function testSectionKeepsTheBendsBetweenItsEnds(): void
    {
        $section = $this->rightAngle()->section(0.5 * self::DEGREE, 1.5 * self::DEGREE);

        $this->assertEqualsWithDelta(self::DEGREE, $section->length(), self::DELTA);
        $this->assertCount(3, $section->points());
        $this->assertPointEquals(new Point(0, 0.5), $section->points()[0]);
        $this->assertPointEquals(new Point(0, 1), $section->points()[1]);
        $this->assertPointEquals(new Point(0.5, 1), $section->points()[2]);
    }

    public function testSectionEndingOnAVertexDoesNotRepeatIt(): void
    {
        $section = $this->rightAngle()->section(0.5 * self::DEGREE, self::DEGREE);

        $this->assertCount(2, $section->points());
        $this->assertPointEquals(new Point(0, 1), $section->points()[1]);
    }

    public function testBackwardsSectionRunsInReverse(): void
    {
        $line = $this->rightAngle();

        $forwards = $line->section(0.5 * self::DEGREE, 1.5 * self::DEGREE);
        $backwards = $line->section(1.5 * self::DEGREE, 0.5 * self::DEGREE);

        $this->assertEqualsWithDelta($forwards->length(), $backwards->length(), self::DELTA);
        $this->assertPointEquals(new Point(0.5, 1), $backwards->points()[0]);
        $this->assertPointEquals(new Point(0, 1), $backwards->points()[1]);
        $this->assertPointEquals(new Point(0, 0.5), $backwards->points()[2]);
    }

    public function testSectionOfTheWholeLine(): void
    {
        $line = $this->rightAngle();
        $section = $line->section(0.0, $line->length());

        $this->assertSame($line->length(), $section->length());
        $this->assertCount(3, $section->points());
    }

    public function testZeroLengthSection(): void
    {
        $section = $this->eastwardLine()->section(0.5 * self::DEGREE, 0.5 * self::DEGREE);

        $this->assertSame(0.0, $section->length());
        $this->assertPointEquals(new Point(0, 0.5), $section->points()[0]);
    }

    public function testSectionRejectsDistancesOffTheLine(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->eastwardLine()->section(0.0, 3 * self::DEGREE);
    }

    public function testSectionLengthMatchesTheDistanceBetweenTwoLocatedPoints(): void
    {
        // A winding line with short segments, like a real river
        $points = [];
        for ($i = 0; $i <= 200; $i++) {
            $points[] = new Point(53.0 + 0.001 * $i, 16.7 + 0.002 * sin($i / 5));
        }
        $river = new Polyline($points);

        $putIn = $river->locate(new Point(53.0312, 16.7031));
        $takeOut = $river->locate(new Point(53.1687, 16.6975));
        $section = $river->section($putIn->distance, $takeOut->distance);

        $this->assertEqualsWithDelta($takeOut->distance - $putIn->distance, $section->length(), 0.5);
        $this->assertGreaterThan($putIn->point->distanceTo($takeOut->point), $section->length());
    }

    // --- helpers --------------------------------------------------------------------------

    /** Two degrees due east along the equator: (0,0) -> (0,1) -> (0,2) */
    private function eastwardLine(): Polyline
    {
        return new Polyline([new Point(0, 0), new Point(0, 1), new Point(0, 2)]);
    }

    /** One degree east along the equator, then one degree north: (0,0) -> (0,1) -> (1,1) */
    private function rightAngle(): Polyline
    {
        return new Polyline([new Point(0, 0), new Point(0, 1), new Point(1, 1)]);
    }

    private function assertPointEquals(Point $expected, Point $actual): void
    {
        $this->assertEqualsWithDelta($expected->latitude, $actual->latitude, 1e-7, 'latitude');
        $this->assertEqualsWithDelta($expected->longitude, $actual->longitude, 1e-7, 'longitude');
    }
}
