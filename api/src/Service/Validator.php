<?php
declare(strict_types=1);

namespace Jbryn\DistanceCalculator\Service;

use Jbryn\DistanceCalculator\Exception\ValidationException;

class Validator
{
    public function validate(mixed $data): void
    {
        if (!is_array($data)) {
            throw new ValidationException('Request body must be a JSON object');
        }

        $required = ['lat1', 'lon1', 'lat2', 'lon2'];
        
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                throw new ValidationException("Missing required field: {$field}");
            }
            
            if (!is_numeric($data[$field])) {
                throw new ValidationException("Field {$field} must be a number");
            }
        }
        
        $this->validateLatitude((float)$data['lat1'], 'lat1');
        $this->validateLatitude((float)$data['lat2'], 'lat2');
        $this->validateLongitude((float)$data['lon1'], 'lon1');
        $this->validateLongitude((float)$data['lon2'], 'lon2');
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