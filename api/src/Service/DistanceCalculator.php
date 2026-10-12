<?php

declare(strict_types=1);

namespace Jbryn\DistanceCalculator\Service;

use Jbryn\DistanceCalculator\Geo\Point;

class DistanceCalculator
{
    /**
     * @return array{meters: float, kilometers: float}
     */
    public function calculate(float $lat1, float $lon1, float $lat2, float $lon2): array
    {
        $distanceInMeters = (new Point($lat1, $lon1))->distanceTo(new Point($lat2, $lon2));

        return [
            'meters' => round($distanceInMeters, 2),
            'kilometers' => round($distanceInMeters / 1000, 2),
        ];
    }
}
