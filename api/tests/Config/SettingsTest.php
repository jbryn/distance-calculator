<?php

declare(strict_types=1);

namespace Jbryn\DistanceCalculator\Tests\Config;

use Jbryn\DistanceCalculator\Config\Settings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SettingsTest extends TestCase
{
    public function testUsesSafeDefaultsWhenEnvironmentIsEmpty(): void
    {
        $settings = Settings::fromEnvironment([]);

        $this->assertFalse($settings->debug);
        $this->assertSame('http://localhost:5173', $settings->corsOrigin);
        $this->assertTrue($settings->logErrors);
    }

    public function testReadsValuesFromEnvironment(): void
    {
        $settings = Settings::fromEnvironment([
            'APP_DEBUG' => 'true',
            'CORS_ORIGIN' => 'https://example.com',
        ]);

        $this->assertTrue($settings->debug);
        $this->assertSame('https://example.com', $settings->corsOrigin);
    }

    #[DataProvider('debugValues')]
    public function testParsesDebugFlag(mixed $value, bool $expected): void
    {
        $this->assertSame($expected, Settings::fromEnvironment(['APP_DEBUG' => $value])->debug);
    }

    /**
     * @return array<array<mixed>>
     */
    public static function debugValues(): array
    {
        return [
            'true' => ['true', true],
            '1' => ['1', true],
            'on' => ['on', true],
            'false' => ['false', false],
            '0' => ['0', false],
            'empty' => ['', false],
            'unrecognised' => ['maybe', false],
            'not a string' => [['true'], false],
        ];
    }

    public function testIgnoresEmptyOrNonStringCorsOrigin(): void
    {
        $this->assertSame('http://localhost:5173', Settings::fromEnvironment(['CORS_ORIGIN' => ''])->corsOrigin);
        $this->assertSame('http://localhost:5173', Settings::fromEnvironment(['CORS_ORIGIN' => 123])->corsOrigin);
    }

    public function testOverpassDefaults(): void
    {
        $settings = Settings::fromEnvironment([]);

        $this->assertSame(Settings::DEFAULT_OVERPASS_ENDPOINTS, $settings->overpassEndpoints);
        $this->assertSame(Settings::DEFAULT_OVERPASS_USER_AGENT, $settings->overpassUserAgent);
        $this->assertSame(Settings::DEFAULT_OVERPASS_CACHE_DIR, $settings->overpassCacheDir);
        $this->assertSame(30 * 24 * 60 * 60, $settings->overpassCacheTtl);
        $this->assertFalse($settings->overpassOffline);
    }

    public function testReadsOverpassValuesFromEnvironment(): void
    {
        $settings = Settings::fromEnvironment([
            'OVERPASS_ENDPOINTS' => 'https://a.example/api, https://b.example/api ,',
            'OVERPASS_USER_AGENT' => 'my-app (me@example.com)',
            'OVERPASS_CACHE_DIR' => '/tmp/overpass',
            'OVERPASS_CACHE_TTL' => '3600',
            'OVERPASS_OFFLINE' => 'true',
        ]);

        $this->assertSame(['https://a.example/api', 'https://b.example/api'], $settings->overpassEndpoints);
        $this->assertSame('my-app (me@example.com)', $settings->overpassUserAgent);
        $this->assertSame('/tmp/overpass', $settings->overpassCacheDir);
        $this->assertSame(3600, $settings->overpassCacheTtl);
        $this->assertTrue($settings->overpassOffline);
    }

    #[DataProvider('unusableCacheLifetimes')]
    public function testFallsBackToTheDefaultCacheLifetime(string $value): void
    {
        $settings = Settings::fromEnvironment(['OVERPASS_CACHE_TTL' => $value]);

        $this->assertSame(Settings::DEFAULT_OVERPASS_CACHE_TTL, $settings->overpassCacheTtl);
    }

    /**
     * @return array<array<mixed>>
     */
    public static function unusableCacheLifetimes(): array
    {
        return [['abc'], ['0'], ['-5'], ['1.5'], ['']];
    }

    public function testFallsBackToTheDefaultServersWhenTheListIsEmpty(): void
    {
        $settings = Settings::fromEnvironment(['OVERPASS_ENDPOINTS' => ' , ']);

        $this->assertSame(Settings::DEFAULT_OVERPASS_ENDPOINTS, $settings->overpassEndpoints);
    }
}
