<?php

declare(strict_types=1);

namespace Jbryn\DistanceCalculator\Overpass;

final class CacheEntry
{
    /**
     * @param list<array<string, mixed>> $elements
     * @param int                        $fetchedAt Unix timestamp of when the response was fetched
     */
    public function __construct(
        public readonly array $elements,
        public readonly int $fetchedAt,
    ) {}
}
