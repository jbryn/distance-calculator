<?php
declare(strict_types=1);

namespace Jbryn\DistanceCalculator\Service;

use Jbryn\DistanceCalculator\Exception\ValidationException;

class Validator
{
    public function validate(array $data): void
    {
        $required = ['lat1', 'lon1', 'lat2', 'lon2'];
        
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                throw new ValidationException("Missing required field: {$field}");
            }
            
            if (!is_numeric($data[$field])) {
                throw new ValidationException("Field {$field} must be a number");
            }

            if (is_int($data[$field]) || !str_contains((string)$data[$field], '.')) {
                throw new ValidationException("Field {$field} must be a floating-point number");
            }
        }
        
        $this->validateLatitude($data['lat1'], 'lat1');
        $this->validateLatitude($data['lat2'], 'lat2');
        $this->validateLongitude($data['lon1'], 'lon1');
        $this->validateLongitude($data['lon2'], 'lon2');
    }

    private function validateLatitude(float $lat, string $field): void
    {
        if ($lat < -90 || $lat > 90) {
            throw new ValidationException(
                "Invalid latitude for {$field}. Value must be between -90 and 90 degrees"
            );
        }
    }

    private function validateLongitude(float $lon, string $field): void
    {
        if ($lon < -180 || $lon > 180) {
            throw new ValidationException(
                "Invalid longitude for {$field}. Value must be between -180 and 180 degrees"
            );
        }
    }
}