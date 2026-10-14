<?php

declare(strict_types=1);

namespace Jbryn\DistanceCalculator\Geo;

use InvalidArgumentException;
use LogicException;

/**
 * A path made of straight segments between consecutive points, such as the course of a river.
 *
 * Positions on the line are expressed as a distance in meters from its first point.
 * Segments are assumed to be short (a few kilometers at most), which lets snapping and
 * interpolation treat each one as flat.
 *
 * For snapping, consecutive segments are grouped into chunks with a bounding box. A chunk
 * whose box is farther from the point than the nearest segment found so far cannot hold
 * a nearer one and is skipped, so a long line is searched in a few chunks, not end to end.
 */
final class Polyline
{
    /** Number of segments per chunk */
    private const CHUNK_SIZE = 32;

    /** @var non-empty-list<Point> */
    private readonly array $points;

    /** @var list<float> meters from the first point to each point */
    private readonly array $cumulative;

    /**
     * @var list<array{first: int, last: int, minLatitude: float, maxLatitude: float, minLongitude: float, maxLongitude: float}>
     *      segments $first to $last (inclusive) and the box around their points
     */
    private readonly array $chunks;

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
        $this->chunks = self::chunks($points);
    }

    /**
     * @return non-empty-list<Point> at least two
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
        // Search the chunks nearest to the point first, so the best match is found early
        $bounds = [];
        foreach ($this->chunks as $index => $chunk) {
            $bounds[$index] = self::lowerBound($point, $chunk);
        }
        asort($bounds);

        $nearest = null;

        foreach ($bounds as $index => $bound) {
            if ($nearest !== null && $bound > $nearest->offset) {
                // Every remaining chunk is at least this far away
                break;
            }

            $chunk = $this->chunks[$index];

            for ($i = $chunk['first']; $i <= $chunk['last']; $i++) {
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
        }

        if ($nearest === null) {
            throw new LogicException('A polyline always has at least one segment');
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

    /**
     * @param list<Point> $points
     *
     * @return list<array{first: int, last: int, minLatitude: float, maxLatitude: float, minLongitude: float, maxLongitude: float}>
     */
    private static function chunks(array $points): array
    {
        $chunks = [];
        $segments = count($points) - 1;

        for ($first = 0; $first < $segments; $first += self::CHUNK_SIZE) {
            $last = min($first + self::CHUNK_SIZE, $segments) - 1;
            $latitudes = [$points[$first]->latitude];
            $longitudes = [$points[$first]->longitude];

            for ($i = $first + 1; $i <= $last + 1; $i++) {
                $latitudes[] = $points[$i]->latitude;
                $longitudes[] = $points[$i]->longitude;
            }

            $chunks[] = [
                'first' => $first,
                'last' => $last,
                'minLatitude' => min($latitudes),
                'maxLatitude' => max($latitudes),
                'minLongitude' => min($longitudes),
                'maxLongitude' => max($longitudes),
            ];
        }

        return $chunks;
    }

    /**
     * Meters that any position in the chunk is at least away from the point.
     *
     * A chunk that crosses the antimeridian gets a box spanning almost every longitude,
     * which makes its bound small: it is searched more often than needed, but never missed.
     *
     * Two facts give the bound, and the larger one is used:
     * - a path that changes latitude by some angle is at least that angle long;
     * - a path to a place a longitude difference of Δλ away must cross the meridian at Δλ,
     *   and the nearest a point at latitude φ comes to that meridian is asin(sin Δλ · cos φ).
     *
     * @param array{first: int, last: int, minLatitude: float, maxLatitude: float, minLongitude: float, maxLongitude: float} $chunk
     */
    private static function lowerBound(Point $point, array $chunk): float
    {
        $latitudeGap = max(0.0, $chunk['minLatitude'] - $point->latitude, $point->latitude - $chunk['maxLatitude']);

        // A box near longitude -180 may be closest to a point near 180, going the other way round
        $longitudeGap = INF;
        foreach ([-360.0, 0.0, 360.0] as $shift) {
            $longitude = $point->longitude + $shift;
            $longitudeGap = min($longitudeGap, max(0.0, $chunk['minLongitude'] - $longitude, $longitude - $chunk['maxLongitude']));
        }

        $alongMeridian = deg2rad($latitudeGap);
        $acrossMeridians = asin(min(1.0, sin(deg2rad(min($longitudeGap, 90.0))) * cos(deg2rad($point->latitude))));

        return Point::EARTH_RADIUS_METERS * max($alongMeridian, $acrossMeridians);
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
