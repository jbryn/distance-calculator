<?php

declare(strict_types=1);

namespace Jbryn\Downriver\Overpass;

use Closure;

/**
 * Answers from saved responses when it can, and asks the wrapped client when it cannot.
 *
 * - A saved response younger than the time-to-live is returned without any request.
 * - An older one is refreshed, but if the refresh fails the old response is still
 *   returned: slightly outdated river data is better than an error.
 */
final class CachedOverpassClient implements OverpassClient
{
    /** @var Closure(): int */
    private readonly Closure $now;

    /**
     * @param int                   $timeToLive seconds a saved response counts as fresh
     * @param (Closure(): int)|null $now        returns the current Unix timestamp; replaceable in tests
     */
    public function __construct(
        private readonly OverpassClient $inner,
        private readonly QueryCache $cache,
        private readonly int $timeToLive,
        ?Closure $now = null,
    ) {
        $this->now = $now ?? time(...);
    }

    public function query(string $query): array
    {
        $saved = $this->cache->get($query);

        if ($saved !== null && ($this->now)() - $saved->fetchedAt < $this->timeToLive) {
            return $saved->elements;
        }

        try {
            $elements = $this->inner->query($query);
        } catch (OverpassUnavailableException $e) {
            if ($saved !== null) {
                return $saved->elements;
            }

            throw $e;
        }

        $this->cache->put($query, $elements);

        return $elements;
    }
}
