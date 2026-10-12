<?php

declare(strict_types=1);

namespace Jbryn\DistanceCalculator\River;

/**
 * Pieces that were not used for the river's line, grouped where they join each other.
 */
final class LeftoverPart
{
    /** Closer than this, in meters, counts as touching the line */
    public const TOUCHING = 1.0;

    /**
     * @param list<int> $wayIds
     * @param float     $length         meters, all pieces together
     * @param float     $distanceToLine meters from the nearest end of the part to the river's line
     */
    public function __construct(
        public readonly array $wayIds,
        public readonly float $length,
        public readonly float $distanceToLine,
    ) {}

    /**
     * True when the part does not reach the line: the river is probably cut in two here.
     * A part that touches the line is a branch, such as a channel around an island.
     */
    public function isDetached(): bool
    {
        return $this->distanceToLine > self::TOUCHING;
    }
}
