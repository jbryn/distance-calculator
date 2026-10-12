<?php

declare(strict_types=1);

namespace Jbryn\DistanceCalculator\Service;

use Jbryn\DistanceCalculator\Exception\ValidationException;

class Validator
{
    /**
     * @return array{lat1: float, lon1: float, lat2: float, lon2: float}
     */
    public function validate(mixed $data): array
    {
        if (!is_array($data)) {
            throw new ValidationException('Request body must be a JSON object');
        }

        $coordinates = [
            'lat1' => $this->number($data, 'lat1'),
            'lon1' => $this->number($data, 'lon1'),
            'lat2' => $this->number($data, 'lat2'),
            'lon2' => $this->number($data, 'lon2'),
        ];

        $this->validateLatitude($coordinates['lat1'], 'lat1');
        $this->validateLatitude($coordinates['lat2'], 'lat2');
        $this->validateLongitude($coordinates['lon1'], 'lon1');
        $this->validateLongitude($coordinates['lon2'], 'lon2');

        return $coordinates;
    }

    /**
     * @param array<mixed> $data
     */
    private function number(array $data, string $field): float
    {
        if (!isset($data[$field])) {
            throw new ValidationException("Missing required field: {$field}");
        }

        if (!is_numeric($data[$field])) {
            throw new ValidationException("Field {$field} must be a number");
        }

        return (float) $data[$field];
    }

    private function validateLatitude(float $lat, string $field): void
    {
        if ($lat < -90 || $lat > 90) {
            throw new ValidationException(
                "Invalid latitude for {$field}. Value must be between -90 and 90 degrees",
            );
        }
    }

    private function validateLongitude(float $lon, string $field): void
    {
        if ($lon < -180 || $lon > 180) {
            throw new ValidationException(
                "Invalid longitude for {$field}. Value must be between -180 and 180 degrees",
            );
        }
    }
}
