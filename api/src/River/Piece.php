<?php

declare(strict_types=1);

namespace Jbryn\DistanceCalculator\River;

use InvalidArgumentException;
use Jbryn\DistanceCalculator\Geo\Point;

/**
 * One of the separate lines a river is stored as in OpenStreetMap (a "way").
 */
final class Piece
{
    /** @var list<Point> */
    public readonly array $points;

    /** Length in meters */
    public readonly float $length;

    /**
     * @param int          $wayId  the OpenStreetMap id of the way, kept so problems can be reported
     * @param array<Point> $points at least two, in the order they were drawn
     */
    public function __construct(
        public readonly int $wayId,
        array $points,
    ) {
        $points = array_values($points);

        if (count($points) < 2) {
            throw new InvalidArgumentException("Way {$wayId} needs at least two points");
        }

        $length = 0.0;
        for ($i = 1, $count = count($points); $i < $count; $i++) {
            $length += $points[$i - 1]->distanceTo($points[$i]);
        }

        $this->points = $points;
        $this->length = $length;
    }

    public function start(): Point
    {
        return $this->points[0];
    }

    public function end(): Point
    {
        return $this->points[count($this->points) - 1];
    }
}
