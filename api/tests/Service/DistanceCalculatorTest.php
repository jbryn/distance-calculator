<?php
declare(strict_types=1);

namespace Jbryn\DistanceCalculator\Tests\Service;

use Jbryn\DistanceCalculator\Service\DistanceCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DistanceCalculatorTest extends TestCase
{
    private const EARTH_RADIUS = 6371000;

    private DistanceCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new DistanceCalculator();
    }

    public function testReturnsZeroForIdenticalPoints(): void
    {
        $result = $this->calculator->calculate(52.229676, 21.012229, 52.229676, 21.012229);

        $this->assertSame(['meters' => 0.0, 'kilometers' => 0.0], $result);
    }

    public function testEquatorToPoleIsQuarterOfCircumference(): void
    {
        $result = $this->calculator->calculate(0.0, 0.0, 90.0, 0.0);

        $this->assertEqualsWithDelta(M_PI / 2 * self::EARTH_RADIUS, $result['meters'], 0.01);
    }

    public function testAntipodalPointsAreHalfOfCircumference(): void
    {
        $result = $this->calculator->calculate(0.0, 0.0, 0.0, 180.0);

        $this->assertEqualsWithDelta(M_PI * self::EARTH_RADIUS, $result['meters'], 0.01);
    }

    #[DataProvider('cityPairs')]
    public function testCalculatesDistanceBetweenCities(
        float $lat1,
        float $lon1,
        float $lat2,
        float $lon2,
        float $expectedKilometers
    ): void {
        $result = $this->calculator->calculate($lat1, $lon1, $lat2, $lon2);

        $this->assertEqualsWithDelta($expectedKilometers, $result['kilometers'], 1.0);
    }

    public static function cityPairs(): array
    {
        return [
            'London - Paris' => [51.5074, -0.1278, 48.8566, 2.3522, 343.5],
            'Warsaw - Krakow' => [52.229676, 21.012229, 50.06465, 19.94498, 252.0],
            'New York - Los Angeles' => [40.7128, -74.0060, 34.0522, -118.2437, 3935.7],
            'Sydney - Buenos Aires (crosses antimeridian)' => [-33.8688, 151.2093, -34.6037, -58.3816, 11801.0],
        ];
    }

    public function testDistanceIsSymmetric(): void
    {
        $there = $this->calculator->calculate(51.5074, -0.1278, 48.8566, 2.3522);
        $back = $this->calculator->calculate(48.8566, 2.3522, 51.5074, -0.1278);

        $this->assertSame($there, $back);
    }

    public function testKilometersMatchMeters(): void
    {
        $result = $this->calculator->calculate(51.5074, -0.1278, 48.8566, 2.3522);

        $this->assertEqualsWithDelta($result['meters'] / 1000, $result['kilometers'], 0.01);
    }

    public function testResultsAreRoundedToTwoDecimalPlaces(): void
    {
        $result = $this->calculator->calculate(51.5074, -0.1278, 48.8566, 2.3522);

        $this->assertSame(round($result['meters'], 2), $result['meters']);
        $this->assertSame(round($result['kilometers'], 2), $result['kilometers']);
    }
}
