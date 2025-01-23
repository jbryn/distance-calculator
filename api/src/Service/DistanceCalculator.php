<?php
declare(strict_types=1);

namespace Jbryn\DistanceCalculator\Service;

class DistanceCalculator
{
    private const EARTH_RADIUS = 6371000;

    public function calculate(float $lat1, float $lon1, float $lat2, float $lon2): array
    {        
        $lat1 = deg2rad($lat1);
        $lon1 = deg2rad($lon1);
        $lat2 = deg2rad($lat2);
        $lon2 = deg2rad($lon2);
        
        $deltaLat = $lat2 - $lat1;
        $deltaLon = $lon2 - $lon1;

        $a = sin($deltaLat/2) * sin($deltaLat/2) +
             cos($lat1) * cos($lat2) *
             sin($deltaLon/2) * sin($deltaLon/2);
             
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        
        $distanceInMeters = self::EARTH_RADIUS * $c;

        return [
            'meters' => round($distanceInMeters, 2),
            'kilometers' => round($distanceInMeters / 1000, 2)
        ];
    }
}