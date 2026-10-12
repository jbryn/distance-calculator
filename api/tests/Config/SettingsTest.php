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
}
