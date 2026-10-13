<?php

declare(strict_types=1);

namespace Jbryn\DistanceCalculator\River;

use Jbryn\DistanceCalculator\Geo\Point;
use Jbryn\DistanceCalculator\Geo\Polyline;

/**
 * A river ready to be measured: one unbroken line from source to mouth.
 */
final class River
{
    /**
     * How far, in meters, a point may be from the line and still be snapped to it.
     * Generous on purpose: the line runs mid-channel, and a wide river or a reservoir
     * puts its banks hundreds of meters away.
     */
    public const MAX_SNAP_DISTANCE = 2000.0;

    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly Polyline $line,
    ) {}

    /**
     * The stretch of the river between two points, each snapped to the nearest place on the line.
     *
     * @throws PointTooFarException
     */
    public function section(Point $from, Point $to): RiverSection
    {
        $start = $this->line->locate($from);
        $end = $this->line->locate($to);

        foreach (['from' => $start, 'to' => $end] as $which => $position) {
            if ($position->offset > self::MAX_SNAP_DISTANCE) {
                throw new PointTooFarException($which, $position->offset, $this->name);
            }
        }

        return new RiverSection($start, $end, $this->line->section($start->distance, $end->distance));
    }
}
