<?php

declare(strict_types=1);

namespace Jbryn\DistanceCalculator\Overpass;

/**
 * Never reaches the network. Wrapped in a cache, it makes the application answer
 * only from saved responses, which is how tests and CI run.
 */
final class OfflineOverpassClient implements OverpassClient
{
    public function query(string $query): array
    {
        throw new OverpassUnavailableException('Overpass is switched off (offline mode) and this query is not saved');
    }
}
