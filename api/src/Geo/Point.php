<?php

declare(strict_types=1);

namespace Jbryn\Downriver\Geo;

use InvalidArgumentException;

/**
 * A position on the Earth's surface, in decimal degrees.
 */
final class Point
{
    public const EARTH_RADIUS_METERS = 6371000;

    public function __construct(
        public readonly float $latitude,
        public readonly float $longitude,
    ) {
        if (!($latitude >= -90 && $latitude <= 90)) {
            throw new InvalidArgumentException("Latitude must be between -90 and 90 degrees, got {$latitude}");
        }

        if (!($longitude >= -180 && $longitude <= 180)) {
            throw new InvalidArgumentException("Longitude must be between -180 and 180 degrees, got {$longitude}");
        }
    }

    /**
     * Great-circle distance in meters (Haversine formula), not rounded.
     */
    public function distanceTo(self $other): float
    {
        $lat1 = deg2rad($this->latitude);
        $lat2 = deg2rad($other->latitude);
        $deltaLat = $lat2 - $lat1;
        $deltaLon = deg2rad($other->longitude - $this->longitude);

        $a = sin($deltaLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($deltaLon / 2) ** 2;

        return self::EARTH_RADIUS_METERS * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    public function equals(self $other): bool
    {
        return $this->latitude === $other->latitude && $this->longitude === $other->longitude;
    }
}
