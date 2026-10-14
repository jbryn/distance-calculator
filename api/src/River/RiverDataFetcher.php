<?php

declare(strict_types=1);

namespace Jbryn\DistanceCalculator\River;

use Closure;
use Jbryn\DistanceCalculator\Overpass\OverpassClient;
use Jbryn\DistanceCalculator\Overpass\OverpassUnavailableException;
use Jbryn\DistanceCalculator\Overpass\QueryCache;

/**
 * Downloads everything the application needs to know about a river and saves it.
 *
 * Used when the saved river data is added to or refreshed, never while serving a request.
 */
final class RiverDataFetcher
{
    /** @var Closure(int): void */
    private readonly Closure $pause;

    /**
     * @param OverpassClient             $source   a client that really asks Overpass
     * @param int                        $attempts how many times to ask before giving up on a query;
     *                                             the public servers often refuse when busy
     * @param (Closure(int): void)|null  $pause    waits the given number of seconds between attempts;
     *                                             replaceable in tests
     */
    public function __construct(
        private readonly OverpassClient $source,
        private readonly QueryCache $cache,
        private readonly int $attempts = 5,
        ?Closure $pause = null,
    ) {
        $this->pause = $pause ?? static function (int $seconds): void {
            sleep($seconds);
        };
    }

    /**
     * The queries that together describe one river: its course, and the features along it.
     *
     * @return list<string>
     */
    public static function queries(int $riverId): array
    {
        return [RiverRelation::overpassQuery($riverId), MapFeature::overpassQuery($riverId)];
    }

    /**
     * Nothing is saved unless every query succeeds, so the saved course and features
     * of a river always come from the same fetch.
     *
     * @throws OverpassUnavailableException when a query still fails on the last attempt
     */
    public function fetch(int $riverId): void
    {
        $answers = [];

        foreach (self::queries($riverId) as $query) {
            $answers[$query] = $this->ask($query);
        }

        foreach ($answers as $query => $elements) {
            $this->cache->put($query, $elements);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function ask(string $query): array
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return $this->source->query($query);
            } catch (OverpassUnavailableException $e) {
                if ($attempt >= $this->attempts) {
                    throw $e;
                }

                // Wait longer each time: 15 s, 30 s, 45 s, ...
                ($this->pause)(15 * $attempt);
            }
        }
    }
}
