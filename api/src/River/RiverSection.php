<?php

declare(strict_types=1);

namespace Jbryn\DistanceCalculator\River;

use Jbryn\DistanceCalculator\Geo\LinePosition;
use Jbryn\DistanceCalculator\Geo\Polyline;

/**
 * A stretch of a river between two positions on it.
 */
final class RiverSection
{
    /**
     * @param LinePosition $from where the stretch starts; its distance is measured from the river's source
     * @param LinePosition $to   where the stretch ends
     * @param Polyline     $line the course of the river from $from to $to
     */
    public function __construct(
        public readonly LinePosition $from,
        public readonly LinePosition $to,
        public readonly Polyline $line,
    ) {}

    /**
     * Meters along the river, the same in either direction.
     */
    public function length(): float
    {
        return abs($this->to->distance - $this->from->distance);
    }

    /**
     * False when the end lies closer to the source than the start, that is, against the current.
     */
    public function isDownstream(): bool
    {
        return $this->to->distance >= $this->from->distance;
    }
}
