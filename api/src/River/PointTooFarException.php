<?php

declare(strict_types=1);

namespace Jbryn\DistanceCalculator\River;

use RuntimeException;

/**
 * A point given as the start or end of a section is not near the river.
 */
final class PointTooFarException extends RuntimeException
{
    /**
     * @param string $which  "from" or "to"
     * @param float  $offset meters between the point and the river
     */
    public function __construct(
        public readonly string $which,
        public readonly float $offset,
        string $riverName,
    ) {
        parent::__construct(sprintf(
            "The '%s' point is %.1f km from the river %s; it must be within %.0f km",
            $which,
            $offset / 1000,
            $riverName,
            River::MAX_SNAP_DISTANCE / 1000,
        ));
    }
}
