<?php

declare(strict_types=1);

namespace Jbryn\DistanceCalculator\River;

use RuntimeException;

/**
 * The river is not one this application offers.
 */
final class RiverNotFoundException extends RuntimeException
{
    public function __construct(public readonly int $riverId)
    {
        parent::__construct("River {$riverId} is not in the catalogue");
    }
}
