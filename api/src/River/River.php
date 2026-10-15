<?php

declare(strict_types=1);

namespace Jbryn\Downriver\River;

use Jbryn\Downriver\Geo\Point;
use Jbryn\Downriver\Geo\Polyline;

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

    /**
     * @param list<RiverFeature> $features obstacles and access points, ordered from source to mouth
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly Polyline $line,
        public readonly array $features = [],
    ) {}

    /**
     * The stretch of the river between two points, each snapped to the nearest place on the line,
     * with the features a paddler meets on the way, in the order they are met.
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

        $nearer = min($start->distance, $end->distance);
        $farther = max($start->distance, $end->distance);

        $features = [];
        foreach ($this->features as $feature) {
            if ($feature->distance >= $nearer && $feature->distance <= $farther) {
                $features[] = $feature->at(abs($feature->distance - $start->distance));
            }
        }

        if ($end->distance < $start->distance) {
            $features = array_reverse($features);
        }

        return new RiverSection($start, $end, $this->line->section($start->distance, $end->distance), $features);
    }
}
