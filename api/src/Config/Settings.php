<?php

declare(strict_types=1);

namespace Jbryn\DistanceCalculator\Config;

final class Settings
{
    public const DEFAULT_CORS_ORIGIN = 'http://localhost:5173';

    public const DEFAULT_OVERPASS_ENDPOINTS = [
        'https://overpass-api.de/api/interpreter',
        'https://overpass.private.coffee/api/interpreter',
    ];

    public const DEFAULT_OVERPASS_USER_AGENT = 'distance-calculator (+https://github.com/jbryn/distance-calculator)';

    public const DEFAULT_OVERPASS_CACHE_DIR = __DIR__ . '/../../var/cache/overpass';

    /** River geometry changes rarely, so saved responses stay fresh for 30 days */
    public const DEFAULT_OVERPASS_CACHE_TTL = 30 * 24 * 60 * 60;

    /**
     * @param list<string> $overpassEndpoints Overpass servers, tried in order
     * @param int          $overpassCacheTtl  seconds a saved Overpass response counts as fresh
     * @param bool         $overpassOffline   answer only from saved responses, never from the network
     */
    public function __construct(
        public readonly bool $debug = false,
        public readonly string $corsOrigin = self::DEFAULT_CORS_ORIGIN,
        public readonly bool $logErrors = true,
        public readonly array $overpassEndpoints = self::DEFAULT_OVERPASS_ENDPOINTS,
        public readonly string $overpassUserAgent = self::DEFAULT_OVERPASS_USER_AGENT,
        public readonly string $overpassCacheDir = self::DEFAULT_OVERPASS_CACHE_DIR,
        public readonly int $overpassCacheTtl = self::DEFAULT_OVERPASS_CACHE_TTL,
        public readonly bool $overpassOffline = false,
    ) {}

    /**
     * @param array<mixed> $env
     */
    public static function fromEnvironment(array $env): self
    {
        return new self(
            debug: filter_var(self::string($env, 'APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN),
            corsOrigin: self::string($env, 'CORS_ORIGIN', self::DEFAULT_CORS_ORIGIN),
            overpassEndpoints: self::list($env, 'OVERPASS_ENDPOINTS', self::DEFAULT_OVERPASS_ENDPOINTS),
            overpassUserAgent: self::string($env, 'OVERPASS_USER_AGENT', self::DEFAULT_OVERPASS_USER_AGENT),
            overpassCacheDir: self::string($env, 'OVERPASS_CACHE_DIR', self::DEFAULT_OVERPASS_CACHE_DIR),
            overpassCacheTtl: self::positiveInt($env, 'OVERPASS_CACHE_TTL', self::DEFAULT_OVERPASS_CACHE_TTL),
            overpassOffline: filter_var(self::string($env, 'OVERPASS_OFFLINE', 'false'), FILTER_VALIDATE_BOOLEAN),
        );
    }

    /**
     * Reads a comma-separated list.
     *
     * @param array<mixed> $env
     * @param list<string> $default
     *
     * @return list<string>
     */
    private static function list(array $env, string $key, array $default): array
    {
        $items = array_filter(
            array_map(trim(...), explode(',', self::string($env, $key, ''))),
            static fn(string $item): bool => $item !== '',
        );

        return $items === [] ? $default : array_values($items);
    }

    /**
     * @param array<mixed> $env
     */
    private static function positiveInt(array $env, string $key, int $default): int
    {
        $value = filter_var(self::string($env, $key, ''), FILTER_VALIDATE_INT);

        return is_int($value) && $value > 0 ? $value : $default;
    }

    /**
     * @param array<mixed> $env
     */
    private static function string(array $env, string $key, string $default): string
    {
        $value = $env[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : $default;
    }
}
