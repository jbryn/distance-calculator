<?php

declare(strict_types=1);

namespace Jbryn\DistanceCalculator\Overpass;

use Closure;
use JsonException;
use RuntimeException;

/**
 * Saves Overpass responses as JSON files, one per query.
 *
 * Each file also records the query and the fetch time, so a saved response
 * can be understood (and committed as a test fixture) on its own.
 */
final class QueryCache
{
    /** @var Closure(): int */
    private readonly Closure $now;

    /**
     * @param (Closure(): int)|null $now returns the current Unix timestamp; replaceable in tests
     */
    public function __construct(
        private readonly string $directory,
        ?Closure $now = null,
    ) {
        $this->now = $now ?? time(...);
    }

    public function get(string $query): ?CacheEntry
    {
        $path = $this->path($query);

        if (!is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return null;
        }

        try {
            $data = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

            if (!is_array($data) || !is_array($data['elements'] ?? null) || !is_int($data['fetchedAt'] ?? null)) {
                return null;
            }

            return new CacheEntry(ElementList::fromMixed($data['elements']), $data['fetchedAt']);
        } catch (JsonException|OverpassUnavailableException) {
            // A damaged file is treated as if nothing was saved
            return null;
        }
    }

    /**
     * @param list<array<string, mixed>> $elements
     */
    public function put(string $query, array $elements): void
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0o775, true) && !is_dir($this->directory)) {
            throw new RuntimeException("Could not create cache directory {$this->directory}");
        }

        $contents = json_encode([
            'query' => self::normalise($query),
            'fetchedAt' => ($this->now)(),
            'elements' => $elements,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        // Write to a temporary file first, so a reader never sees a half-written entry
        $path = $this->path($query);
        $temporary = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';

        if (file_put_contents($temporary, $contents) === false || !rename($temporary, $path)) {
            throw new RuntimeException("Could not write cache file {$path}");
        }
    }

    public function path(string $query): string
    {
        return $this->directory . '/' . substr(hash('sha256', self::normalise($query)), 0, 32) . '.json';
    }

    /**
     * Two queries that differ only in whitespace are the same query.
     */
    private static function normalise(string $query): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $query));
    }
}
