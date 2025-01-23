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
                throw new ValidationException("Brak wymaganego pola: {$field}");
            }
            
            if (!is_numeric($data[$field])) {
                throw new ValidationException("Pole {$field} musi być liczbą");
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
                "Nieprawidłowa szerokość geograficzna dla {$field}. Wartość musi być między -90 a 90 stopni"
            );
        }
    }

    private function validateLongitude(float $lon, string $field): void
    {
        if ($lon < -180 || $lon > 180) {
            throw new ValidationException(
                "Nieprawidłowa długość geograficzna dla {$field}. Wartość musi być między -180 a 180 stopni"
            );
        }
    }
}