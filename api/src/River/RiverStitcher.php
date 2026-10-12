<?php

declare(strict_types=1);

namespace Jbryn\DistanceCalculator\River;

use InvalidArgumentException;
use Jbryn\DistanceCalculator\Geo\Point;
use Jbryn\DistanceCalculator\Geo\Polyline;
use SplPriorityQueue;

/**
 * Joins the pieces of a river into one line running from source to mouth.
 *
 * Pieces join where one ends exactly on the end of another, which is how OpenStreetMap
 * stores connected ways. The order of the pieces and the direction they were drawn in
 * do not matter for finding the line:
 *
 * 1. Among the pieces that are connected to each other, the line is the longest route
 *    from one loose end to another. Shorter branches are left out; where the river
 *    splits and rejoins, the shorter channel is taken.
 * 2. Rivers are drawn in the direction of flow, so the line runs the way most of its
 *    length was drawn, and pieces drawn the other way are turned round.
 * 3. Everything else is reported as leftovers. The line is never continued across a
 *    gap: a leftover that does not touch the line shows up in StitchedRiver::gaps().
 */
final class RiverStitcher
{
    /**
     * @param array<Piece> $pieces in any order
     */
    public function stitch(array $pieces): StitchedRiver
    {
        $pieces = array_values($pieces);

        if ($pieces === []) {
            throw new InvalidArgumentException('A river needs at least one piece');
        }

        $route = self::orient(self::longestRoute($pieces), $pieces);

        $points = [];
        $wayIds = [];
        $reversedWayIds = [];
        $used = [];

        foreach ($route as [$index, $forward]) {
            $piece = $pieces[$index];
            $piecePoints = $forward ? $piece->points : array_reverse($piece->points);

            // The first point of a piece repeats the last point of the one before it
            array_push($points, ...($points === [] ? $piecePoints : array_slice($piecePoints, 1)));

            $wayIds[] = $piece->wayId;
            $used[$index] = true;

            if (!$forward) {
                $reversedWayIds[] = $piece->wayId;
            }
        }

        $line = new Polyline($points);

        return new StitchedRiver($line, $wayIds, $reversedWayIds, self::leftovers($pieces, $used, $line));
    }

    /**
     * The longest route between two loose ends, as piece indexes with the direction each
     * piece is travelled in (true = as drawn).
     *
     * Within one group of connected pieces, the far end of the route is the junction
     * farthest from an arbitrary one, and the near end is the junction farthest from
     * that. This finds the longest route exactly unless the group contains a loop.
     *
     * @param list<Piece> $pieces
     *
     * @return list<array{int, bool}>
     */
    private static function longestRoute(array $pieces): array
    {
        $junctions = [];
        foreach ($pieces as $index => $piece) {
            $junctions[self::key($piece->start())][] = $index;
            $junctions[self::key($piece->end())][] = $index;
        }

        $route = [];
        $routeLength = 0.0;
        $seen = [];

        foreach (array_keys($junctions) as $junction) {
            if (isset($seen[$junction])) {
                continue;
            }

            [$distances] = self::explore($junction, $pieces, $junctions);
            $seen += $distances;

            [$distances, $arrivedBy] = self::explore(self::farthest($distances), $pieces, $junctions);
            $end = self::farthest($distances);

            if ($distances[$end] > $routeLength) {
                $routeLength = $distances[$end];
                $route = self::trace($end, $arrivedBy, $pieces);
            }
        }

        if ($route === []) {
            // Nothing but closed loops: fall back to the longest piece as it was drawn
            $longest = 0;
            foreach ($pieces as $index => $piece) {
                if ($piece->length > $pieces[$longest]->length) {
                    $longest = $index;
                }
            }

            return [[$longest, true]];
        }

        return $route;
    }

    /**
     * Shortest distance along the pieces from one junction to every junction it is
     * connected to (Dijkstra's algorithm), and the piece each one was reached by.
     *
     * @param list<Piece>              $pieces
     * @param array<string, list<int>> $junctions
     *
     * @return array{non-empty-array<string, float>, array<string, int>}
     */
    private static function explore(string $from, array $pieces, array $junctions): array
    {
        $distances = [$from => 0.0];
        $arrivedBy = [];

        /** @var SplPriorityQueue<float, string> $queue */
        $queue = new SplPriorityQueue();
        $queue->setExtractFlags(SplPriorityQueue::EXTR_BOTH);
        $queue->insert($from, 0.0);

        while (!$queue->isEmpty()) {
            /** @var array{data: string, priority: float} $next */
            $next = $queue->extract();
            $junction = $next['data'];

            // The queue hands out the highest priority first, so priorities are negated distances
            if (-$next['priority'] > $distances[$junction]) {
                continue;
            }

            foreach ($junctions[$junction] as $index) {
                $piece = $pieces[$index];
                $neighbour = self::otherEnd($piece, $junction);
                $distance = $distances[$junction] + $piece->length;

                if (!isset($distances[$neighbour]) || $distance < $distances[$neighbour]) {
                    $distances[$neighbour] = $distance;
                    $arrivedBy[$neighbour] = $index;
                    $queue->insert($neighbour, -$distance);
                }
            }
        }

        return [$distances, $arrivedBy];
    }

    /**
     * @param non-empty-array<string, float> $distances
     */
    private static function farthest(array $distances): string
    {
        $farthest = array_key_first($distances);

        foreach ($distances as $junction => $distance) {
            if ($distance > $distances[$farthest]) {
                $farthest = $junction;
            }
        }

        return $farthest;
    }

    /**
     * Walks back from the end of a route to where explore() started.
     *
     * @param array<string, int> $arrivedBy
     * @param list<Piece>        $pieces
     *
     * @return list<array{int, bool}>
     */
    private static function trace(string $end, array $arrivedBy, array $pieces): array
    {
        $route = [];
        $junction = $end;

        while (isset($arrivedBy[$junction])) {
            $index = $arrivedBy[$junction];
            $piece = $pieces[$index];

            array_unshift($route, [$index, self::key($piece->end()) === $junction]);
            $junction = self::otherEnd($piece, $junction);
        }

        return $route;
    }

    /**
     * Turns the whole route round when most of its length was drawn the other way.
     *
     * @param list<array{int, bool}> $route
     * @param list<Piece>            $pieces
     *
     * @return list<array{int, bool}>
     */
    private static function orient(array $route, array $pieces): array
    {
        $asDrawn = 0.0;
        $againstDrawn = 0.0;

        foreach ($route as [$index, $forward]) {
            if ($forward) {
                $asDrawn += $pieces[$index]->length;
            } else {
                $againstDrawn += $pieces[$index]->length;
            }
        }

        if ($againstDrawn <= $asDrawn) {
            return $route;
        }

        return array_map(
            static fn(array $step): array => [$step[0], !$step[1]],
            array_reverse($route),
        );
    }

    /**
     * Groups the unused pieces by shared ends and measures how far each group is from the line.
     *
     * @param list<Piece>      $pieces
     * @param array<int, true> $used
     *
     * @return list<LeftoverPart>
     */
    private static function leftovers(array $pieces, array $used, Polyline $line): array
    {
        $junctions = [];
        foreach ($pieces as $index => $piece) {
            if (!isset($used[$index])) {
                $junctions[self::key($piece->start())][] = $index;
                $junctions[self::key($piece->end())][] = $index;
            }
        }

        $parts = [];
        $grouped = [];

        foreach ($pieces as $first => $firstPiece) {
            if (isset($used[$first]) || isset($grouped[$first])) {
                continue;
            }

            $wayIds = [];
            $length = 0.0;
            $distanceToLine = INF;
            $pending = [$first];
            $grouped[$first] = true;

            while ($pending !== []) {
                $piece = $pieces[array_pop($pending)];
                $wayIds[] = $piece->wayId;
                $length += $piece->length;

                foreach ([$piece->start(), $piece->end()] as $end) {
                    $distanceToLine = min($distanceToLine, $line->locate($end)->offset);

                    foreach ($junctions[self::key($end)] as $neighbour) {
                        if (!isset($grouped[$neighbour])) {
                            $grouped[$neighbour] = true;
                            $pending[] = $neighbour;
                        }
                    }
                }
            }

            $parts[] = new LeftoverPart($wayIds, $length, $distanceToLine);
        }

        return $parts;
    }

    private static function otherEnd(Piece $piece, string $junction): string
    {
        $start = self::key($piece->start());

        return $start === $junction ? self::key($piece->end()) : $start;
    }

    /**
     * Connected ways share a node, so their ends have exactly the same coordinates.
     */
    private static function key(Point $point): string
    {
        return $point->latitude . ',' . $point->longitude;
    }
}
