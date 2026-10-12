<?php

declare(strict_types=1);

namespace Jbryn\DistanceCalculator\Tests\Geo;

use InvalidArgumentException;
use Jbryn\DistanceCalculator\Geo\Point;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PointTest extends TestCase
{
    public function testHoldsCoordinates(): void
    {
        $point = new Point(53.1, 16.7);

        $this->assertSame(53.1, $point->latitude);
        $this->assertSame(16.7, $point->longitude);
    }

    public function testAcceptsBoundaryValues(): void
    {
        $this->assertSame(-90.0, (new Point(-90, -180))->latitude);
        $this->assertSame(180.0, (new Point(90, 180))->longitude);
    }

    #[DataProvider('invalidCoordinates')]
    public function testRejectsInvalidCoordinates(float $latitude, float $longitude, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        new Point($latitude, $longitude);
    }

    /**
     * @return array<array<mixed>>
     */
    public static function invalidCoordinates(): array
    {
        return [
            'latitude too high' => [90.0001, 0.0, 'Latitude must be between -90 and 90'],
            'latitude too low' => [-90.0001, 0.0, 'Latitude must be between -90 and 90'],
            'latitude not a number' => [NAN, 0.0, 'Latitude must be between -90 and 90'],
            'longitude too high' => [0.0, 180.0001, 'Longitude must be between -180 and 180'],
            'longitude too low' => [0.0, -180.0001, 'Longitude must be between -180 and 180'],
            'longitude not a number' => [0.0, NAN, 'Longitude must be between -180 and 180'],
        ];
    }

    public function testDistanceToItselfIsZero(): void
    {
        $point = new Point(53.1, 16.7);

        $this->assertSame(0.0, $point->distanceTo($point));
    }

    public function testDistanceOfOneDegreeAlongAMeridian(): void
    {
        $oneDegree = M_PI / 180 * Point::EARTH_RADIUS_METERS;

        $this->assertEqualsWithDelta($oneDegree, (new Point(0, 0))->distanceTo(new Point(1, 0)), 0.001);
        $this->assertEqualsWithDelta($oneDegree, (new Point(52, 16))->distanceTo(new Point(53, 16)), 0.001);
    }

    public function testDegreeOfLongitudeShrinksWithLatitude(): void
    {
        $atEquator = (new Point(0, 0))->distanceTo(new Point(0, 1));
        $at60North = (new Point(60, 0))->distanceTo(new Point(60, 1));

        // cos(60°) = 0.5, so a degree of longitude is half as long
        $this->assertEqualsWithDelta(0.5, $at60North / $atEquator, 0.0001);
    }

    public function testDistanceAcrossTheAntimeridianTakesTheShortWay(): void
    {
        $twoDegrees = 2 * M_PI / 180 * Point::EARTH_RADIUS_METERS;

        $this->assertEqualsWithDelta($twoDegrees, (new Point(0, 179))->distanceTo(new Point(0, -179)), 0.001);
    }

    public function testDistanceIsNotRounded(): void
    {
        $distance = (new Point(51.5074, -0.1278))->distanceTo(new Point(48.8566, 2.3522));

        $this->assertEqualsWithDelta(343556.06, $distance, 0.005);
        $this->assertNotSame(round($distance, 2), $distance);
    }

    public function testEquality(): void
    {
        $this->assertTrue((new Point(53.1, 16.7))->equals(new Point(53.1, 16.7)));
        $this->assertFalse((new Point(53.1, 16.7))->equals(new Point(53.1, 16.8)));
    }
}
