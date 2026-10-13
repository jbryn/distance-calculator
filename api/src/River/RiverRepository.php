<?php

declare(strict_types=1);

namespace Jbryn\DistanceCalculator\River;

use Jbryn\DistanceCalculator\Overpass\OverpassClient;
use Jbryn\DistanceCalculator\Overpass\OverpassUnavailableException;

/**
 * Turns a river from the catalogue into a line that can be measured.
 */
final class RiverRepository
{
    public function __construct(
        private readonly RiverCatalogue $catalogue,
        private readonly OverpassClient $overpass,
        private readonly RiverStitcher $stitcher,
    ) {}

    /**
     * @throws RiverNotFoundException    when the river is not in the catalogue
     * @throws RiverUnavailableException when its map data is missing, malformed or has gaps
     */
    public function get(int $id): River
    {
        $summary = $this->catalogue->find($id) ?? throw new RiverNotFoundException($id);

        try {
            $elements = $this->overpass->query(RiverRelation::overpassQuery($id));
        } catch (OverpassUnavailableException $e) {
            throw new RiverUnavailableException("No map data for river {$id}: {$e->getMessage()}", 0, $e);
        }

        if ($elements === []) {
            throw new RiverUnavailableException("OpenStreetMap has no relation {$id}");
        }

        try {
            $pieces = RiverRelation::fromElement($elements[0])->pieces;
        } catch (InvalidRiverDataException $e) {
            throw new RiverUnavailableException("Unusable map data for river {$id}: {$e->getMessage()}", 0, $e);
        }

        if ($pieces === []) {
            throw new RiverUnavailableException("Relation {$id} has no main-course ways");
        }

        $stitched = $this->stitcher->stitch($pieces);

        if (!$stitched->isContinuous()) {
            // Measuring along a line with a hole in it would give a wrong distance that looks right
            throw new RiverUnavailableException(sprintf(
                'River %d has %d gap(s) in its map data',
                $id,
                count($stitched->gaps()),
            ));
        }

        return new River($summary->id, $summary->name, $stitched->line);
    }
}
