<?php

declare(strict_types=1);

namespace Jbryn\DistanceCalculator\Tests\River;

use Jbryn\DistanceCalculator\Geo\Point;
use Jbryn\DistanceCalculator\Geo\Polyline;
use Jbryn\DistanceCalculator\River\PointTooFarException;
use Jbryn\DistanceCalculator\River\River;
use PHPUnit\Framework\TestCase;

/**
 * The invented river flows east along the equator from longitude 0 to 3,
 * where one degree is exactly DEGREE meters.
 */
class RiverTest extends TestCase
{
    /** Length of one degree of arc: 111,194.93 m */
    private const DEGREE = M_PI / 180 * Point::EARTH_RADIUS_METERS;

    /** Tolerance in meters */
    private const DELTA = 0.01;

    public function testSectionBetweenTwoPointsOnTheRiver(): void
    {
        $section = self::river()->section(new Point(0, 0.5), new Point(0, 2.5));

        $this->assertEqualsWithDelta(2 * self::DEGREE, $section->length(), self::DELTA);
        $this->assertTrue($section->isDownstream());
        $this->assertEqualsWithDelta(0.5 * self::DEGREE, $section->from->distance, self::DELTA);
        $this->assertEqualsWithDelta(2.5 * self::DEGREE, $section->to->distance, self::DELTA);
        $this->assertEquals(
            [new Point(0, 0.5), new Point(0, 1), new Point(0, 2), new Point(0, 2.5)],
            $section->line->points(),
        );
    }

    public function testPointsBesideTheRiverAreSnappedToIt(): void
    {
        // 0.01 degrees of latitude is 1.1 km
        $section = self::river()->section(new Point(0.01, 1), new Point(-0.01, 2));

        $this->assertEqualsWithDelta(self::DEGREE, $section->length(), self::DELTA);
        $this->assertEqualsWithDelta(0.01 * self::DEGREE, $section->from->offset, self::DELTA);
        $this->assertEqualsWithDelta(0.0, $section->from->point->latitude, 1e-9);
        $this->assertEqualsWithDelta(1.0, $section->from->point->longitude, 1e-9);
    }

    public function testASectionAgainstTheCurrentHasTheSameLengthAndRunsBackwards(): void
    {
        $section = self::river()->section(new Point(0, 2.5), new Point(0, 0.5));

        $this->assertEqualsWithDelta(2 * self::DEGREE, $section->length(), self::DELTA);
        $this->assertFalse($section->isDownstream());
        $this->assertEquals(
            [new Point(0, 2.5), new Point(0, 2), new Point(0, 1), new Point(0, 0.5)],
            $section->line->points(),
        );
    }

    public function testTheSamePointTwiceIsASectionOfNoLength(): void
    {
        $section = self::river()->section(new Point(0, 1.5), new Point(0, 1.5));

        $this->assertSame(0.0, $section->length());
        $this->assertTrue($section->isDownstream());
        $this->assertEquals([new Point(0, 1.5), new Point(0, 1.5)], $section->line->points());
    }

    public function testAcceptsAPointJustInsideTheLimit(): void
    {
        // 0.0179 degrees of latitude is 1,990 m
        $section = self::river()->section(new Point(0.0179, 1), new Point(0, 2));

        $this->assertEqualsWithDelta(1990.4, $section->from->offset, 0.1);
    }

    public function testRejectsAStartTooFarFromTheRiver(): void
    {
        // 0.0181 degrees of latitude is 2,013 m
        try {
            self::river()->section(new Point(0.0181, 1), new Point(0, 2));
            $this->fail('Expected a PointTooFarException');
        } catch (PointTooFarException $e) {
            $this->assertSame('from', $e->which);
            $this->assertEqualsWithDelta(2012.6, $e->offset, 0.1);
            $this->assertSame(
                "The 'from' point is 2.0 km from the river Testowa; it must be within 2 km",
                $e->getMessage(),
            );
        }
    }

    public function testRejectsAnEndTooFarFromTheRiver(): void
    {
        $this->expectException(PointTooFarException::class);
        $this->expectExceptionMessage("The 'to' point is 111.2 km from the river Testowa; it must be within 2 km");

        self::river()->section(new Point(0, 1), new Point(1, 2));
    }

    private static function river(): River
    {
        return new River(1, 'Testowa', new Polyline([
            new Point(0, 0),
            new Point(0, 1),
            new Point(0, 2),
            new Point(0, 3),
        ]));
    }
}
