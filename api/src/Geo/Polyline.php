<?php

declare(strict_types=1);

namespace Jbryn\DistanceCalculator\Geo;

use InvalidArgumentException;

/**
 * A path made of straight segments between consecutive points, such as the course of a river.
 *
 * Positions on the line are expressed as a distance in meters from its first point.
 * Segments are assumed to be short (a few kilometers at most), which lets snapping and
 * interpolation treat each one as flat.
 */
final class Polyline
{
    /** @var list<Point> */
    private readonly array $points;

    /** @var list<float> meters from the first point to each point */
    private readonly array $cumulative;

    /**
     * @param array<Point> $points at least two, in order
     */
    public function __construct(array $points)
    {
        $points = array_values($points);

        if (count($points) < 2) {
            throw new InvalidArgumentException('A polyline needs at least two points');
        }

        $cumulative = [0.0];
        for ($i = 1, $count = count($points); $i < $count; $i++) {
            $cumulative[] = $cumulative[$i - 1] + $points[$i - 1]->distanceTo($points[$i]);
        }

        $this->points = $points;
        $this->cumulative = $cumulative;
    }

    /**
     * @return list<Point>
     */
    public function points(): array
    {
        return $this->points;
    }

    /**
     * Total length in meters.
     */
    public function length(): float
    {
        return $this->cumulative[count($this->cumulative) - 1];
    }

    /**
     * Snaps a point to the nearest position on the line.
     */
    public function locate(Point $point): LinePosition
    {
        $nearest = null;

        for ($i = 0, $last = count($this->points) - 1; $i < $last; $i++) {
            $start = $this->points[$i];
            $end = $this->points[$i + 1];

            $fraction = self::projectOntoSegment($point, $start, $end);
            $snapped = self::interpolate($start, $end, $fraction);
            $offset = $point->distanceTo($snapped);

            if ($nearest === null || $offset < $nearest->offset) {
                $segmentLength = $this->cumulative[$i + 1] - $this->cumulative[$i];
                $nearest = new LinePosition($this->cumulative[$i] + $fraction * $segmentLength, $snapped, $offset);
            }
        }

        return $nearest;
    }

    /**
     * The point at a given distance in meters from the start of the line.
     */
    public function pointAt(float $distance): Point
    {
        $this->assertOnLine($distance);

        $i = $this->segmentContaining($distance);
        $segmentLength = $this->cumulative[$i + 1] - $this->cumulative[$i];
        $fraction = $segmentLength > 0.0 ? ($distance - $this->cumulative[$i]) / $segmentLength : 0.0;

        return self::interpolate($this->points[$i], $this->points[$i + 1], $fraction);
    }

    /**
     * The part of the line between two distances, ordered from $from to $to.
     * When $from is greater than $to the section runs backwards along the line.
     */
    public function section(float $from, float $to): self
    {
        if ($from > $to) {
            return new self(array_reverse($this->section($to, $from)->points));
        }

        $points = [$this->pointAt($from)];

        foreach ($this->points as $i => $vertex) {
            if ($this->cumulative[$i] > $from && $this->cumulative[$i] < $to) {
                $points[] = $vertex;
            }
        }

        $points[] = $this->pointAt($to);

        return new self($points);
    }

    private function assertOnLine(float $distance): void
    {
        if (!($distance >= 0.0 && $distance <= $this->length())) {
            throw new InvalidArgumentException(
                "Distance {$distance} m is outside the line (0 to {$this->length()} m)",
            );
        }
    }

    /**
     * Index of the segment that contains the given distance (binary search).
     */
    private function segmentContaining(float $distance): int
    {
        $low = 0;
        $high = count($this->points) - 2;

        while ($low < $high) {
            $middle = intdiv($low + $high, 2);

            if ($this->cumulative[$middle + 1] < $distance) {
                $low = $middle + 1;
            } else {
                $high = $middle;
            }
        }

        return $low;
    }

    /**
     * How far along the segment (0 = start, 1 = end) the closest position to $point lies.
     *
     * Works on a flat projection centred on $point: longitude is scaled by cos(latitude)
     * so that one unit means the same ground distance in both directions.
     */
    private static function projectOntoSegment(Point $point, Point $start, Point $end): float
    {
        $scale = cos(deg2rad($point->latitude));

        $startX = self::longitudeDifference($point->longitude, $start->longitude) * $scale;
        $startY = $start->latitude - $point->latitude;
        $deltaX = self::longitudeDifference($start->longitude, $end->longitude) * $scale;
        $deltaY = $end->latitude - $start->latitude;

        $lengthSquared = $deltaX ** 2 + $deltaY ** 2;

        if ($lengthSquared === 0.0) {
            return 0.0;
        }

        $fraction = -($startX * $deltaX + $startY * $deltaY) / $lengthSquared;

        return max(0.0, min(1.0, $fraction));
    }

    private static function interpolate(Point $start, Point $end, float $fraction): Point
    {
        if ($fraction <= 0.0) {
            return $start;
        }

        if ($fraction >= 1.0) {
            return $end;
        }

        $longitude = $start->longitude + $fraction * self::longitudeDifference($start->longitude, $end->longitude);

        return new Point(
            $start->latitude + $fraction * ($end->latitude - $start->latitude),
            self::wrapLongitude($longitude),
        );
    }

    /**
     * Degrees of longitude from $from to $to going the short way round, so that
     * a segment crossing the antimeridian (179 to -179) is 2 degrees long, not 358.
     */
    private static function longitudeDifference(float $from, float $to): float
    {
        return self::wrapLongitude($to - $from);
    }

    private static function wrapLongitude(float $longitude): float
    {
        if ($longitude > 180.0) {
            return $longitude - 360.0;
        }

        if ($longitude < -180.0) {
            return $longitude + 360.0;
        }

        return $longitude;
    }
}
