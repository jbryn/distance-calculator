<?php

declare(strict_types=1);

namespace Jbryn\Downriver\River;

use Jbryn\Downriver\Overpass\OverpassClient;
use Jbryn\Downriver\Overpass\OverpassUnavailableException;

/**
 * Turns a river from the catalogue into a line that can be measured.
 */
final class RiverRepository
{
    public function __construct(
        private readonly RiverCatalogue $catalogue,
        private readonly OverpassClient $overpass,
        private readonly RiverStitcher $stitcher,
        private readonly FeatureLocator $locator,
    ) {}

    /**
     * @throws RiverNotFoundException    when the river is not in the catalogue
     * @throws RiverUnavailableException when its map data is missing, malformed or has gaps.
     *                                   That includes the data on obstacles: a river shown without
     *                                   its weirs would look safer than it is.
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

        return new River(
            $summary->id,
            $summary->name,
            $stitched->line,
            $this->locator->locate($stitched->line, $this->mapFeatures($id)),
        );
    }

    /**
     * @return list<MapFeature>
     */
    private function mapFeatures(int $id): array
    {
        try {
            $elements = $this->overpass->query(MapFeature::overpassQuery($id));
        } catch (OverpassUnavailableException $e) {
            throw new RiverUnavailableException("No obstacle data for river {$id}: {$e->getMessage()}", 0, $e);
        }

        $features = [];

        try {
            foreach ($elements as $element) {
                $feature = MapFeature::fromElement($element);

                if ($feature !== null) {
                    $features[] = $feature;
                }
            }
        } catch (InvalidRiverDataException $e) {
            throw new RiverUnavailableException("Unusable obstacle data for river {$id}: {$e->getMessage()}", 0, $e);
        }

        return $features;
    }
}
