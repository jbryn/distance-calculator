<?php

declare(strict_types=1);

namespace Jbryn\DistanceCalculator\River;

use InvalidArgumentException;
use Jbryn\DistanceCalculator\Geo\Point;

/**
 * A river as OpenStreetMap describes it: a "relation" that lists the ways it is made of.
 *
 * Only the ways that carry the main course are kept. Each member way has a role:
 * "main_stream", "side_stream" (a channel around an island, an old river bed), and a few
 * rarer ones. Many rivers are mapped without roles at all, so an empty role counts as
 * main course too; RiverStitcher then leaves out whatever does not fit into one line.
 */
final class RiverRelation
{
    private const MAIN_COURSE_ROLES = ['main_stream', ''];

    /**
     * @param list<Piece> $pieces the main-course ways, in the order the relation lists them
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly array $pieces,
    ) {}

    /**
     * The Overpass query whose answer fromElement() understands.
     */
    public static function overpassQuery(int $id): string
    {
        return "[out:json][timeout:60];relation({$id});out body geom;";
    }

    /**
     * @param array<string, mixed> $element a relation from an Overpass response that includes geometry
     *
     * @throws InvalidRiverDataException
     */
    public static function fromElement(array $element): self
    {
        $id = $element['id'] ?? null;
        $members = $element['members'] ?? null;

        if (($element['type'] ?? null) !== 'relation' || !is_int($id) || !is_array($members)) {
            throw new InvalidRiverDataException('Expected a relation with an id and members');
        }

        $pieces = [];

        foreach ($members as $member) {
            if (!is_array($member) || ($member['type'] ?? null) !== 'way') {
                continue;
            }

            if (!in_array($member['role'] ?? '', self::MAIN_COURSE_ROLES, true)) {
                continue;
            }

            $pieces[] = self::piece($id, $member);
        }

        $tags = $element['tags'] ?? null;
        $name = is_array($tags) && is_string($tags['name'] ?? null) ? $tags['name'] : '';

        return new self($id, $name, $pieces);
    }

    /**
     * @param array<mixed> $member
     */
    private static function piece(int $relationId, array $member): Piece
    {
        $wayId = $member['ref'] ?? null;
        $geometry = $member['geometry'] ?? null;

        if (!is_int($wayId)) {
            throw new InvalidRiverDataException("Relation {$relationId} has a way without an id");
        }

        if (!is_array($geometry)) {
            throw new InvalidRiverDataException(
                "Way {$wayId} in relation {$relationId} has no geometry; the query must end with \"out geom\"",
            );
        }

        $points = [];

        foreach ($geometry as $position) {
            $latitude = is_array($position) ? ($position['lat'] ?? null) : null;
            $longitude = is_array($position) ? ($position['lon'] ?? null) : null;

            if (!(is_int($latitude) || is_float($latitude)) || !(is_int($longitude) || is_float($longitude))) {
                throw new InvalidRiverDataException("Way {$wayId} in relation {$relationId} has a point without coordinates");
            }

            try {
                $points[] = new Point((float) $latitude, (float) $longitude);
            } catch (InvalidArgumentException $e) {
                throw new InvalidRiverDataException("Way {$wayId} in relation {$relationId}: {$e->getMessage()}", 0, $e);
            }
        }

        if (count($points) < 2) {
            throw new InvalidRiverDataException("Way {$wayId} in relation {$relationId} has fewer than two points");
        }

        return new Piece($wayId, $points);
    }
}
