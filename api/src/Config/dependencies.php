<?php
declare(strict_types=1);

use Jbryn\DistanceCalculator\Service\DistanceCalculator;
use Jbryn\DistanceCalculator\Service\Validator;

return [
    DistanceCalculator::class => \DI\create(DistanceCalculator::class),
    Validator::class => \DI\create(Validator::class)
];