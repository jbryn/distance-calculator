<?php

declare(strict_types=1);

namespace Jbryn\Downriver\Geo;

/**
 * The result of snapping a point to a line.
 */
final class LinePosition
{
    /**
     * @param float $distance meters along the line from its first point
     * @param Point $point    the position on the line itself
     * @param float $offset   meters between the original point and the line
     */
    public function __construct(
        public readonly float $distance,
        public readonly Point $point,
        public readonly float $offset,
    ) {}
}
