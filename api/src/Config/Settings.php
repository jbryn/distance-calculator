<?php

declare(strict_types=1);

namespace Jbryn\DistanceCalculator\Config;

final class Settings
{
    public const DEFAULT_CORS_ORIGIN = 'http://localhost:5173';

    public function __construct(
        public readonly bool $debug = false,
        public readonly string $corsOrigin = self::DEFAULT_CORS_ORIGIN,
        public readonly bool $logErrors = true,
    ) {}

    /**
     * @param array<mixed> $env
     */
    public static function fromEnvironment(array $env): self
    {
        return new self(
            debug: filter_var(self::string($env, 'APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN),
            corsOrigin: self::string($env, 'CORS_ORIGIN', self::DEFAULT_CORS_ORIGIN),
        );
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
