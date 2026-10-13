<?php

declare(strict_types=1);

namespace Jbryn\DistanceCalculator\River;

use Jbryn\DistanceCalculator\Geo\Polyline;

/**
 * Works out where along a river each nearby map feature lies.
 */
final class FeatureLocator
{
    /**
     * Snaps every feature to the line, drops those too far from it to belong to the
     * river, and merges the ones that are the same place mapped more than once.
     *
     * @param array<MapFeature> $features
     *
     * @return list<RiverFeature> ordered from source to mouth
     */
    public function locate(Polyline $line, array $features): array
    {
        $located = [];

        foreach ($features as $feature) {
            $position = $line->locate($feature->point);

            if ($position->offset <= $feature->kind->maxOffset()) {
                $located[] = new RiverFeature($feature->kind, $feature->name, $position->point, $position->distance);
            }
        }

        usort($located, static fn(RiverFeature $a, RiverFeature $b): int => $a->distance <=> $b->distance);

        return self::merge($located);
    }

    /**
     * @param list<RiverFeature> $features ordered by distance
     *
     * @return list<RiverFeature>
     */
    private static function merge(array $features): array
    {
        $merged = [];

        /** @var array<string, int> $latest index in $merged of the latest feature of each kind */
        $latest = [];

        foreach ($features as $feature) {
            $kind = $feature->kind->value;
            $previous = isset($latest[$kind]) ? $merged[$latest[$kind]] : null;

            if ($previous !== null && $feature->distance - $previous->distance < $feature->kind->mergeWithin()) {
                // The place stays where it was first met; it takes a name from a duplicate if it has none
                if ($previous->name === null && $feature->name !== null) {
                    $merged[$latest[$kind]] = new RiverFeature($previous->kind, $feature->name, $previous->point, $previous->distance);
                }

                continue;
            }

            $latest[$kind] = count($merged);
            $merged[] = $feature;
        }

        return array_values($merged);
    }
}
