<?php

declare(strict_types=1);

namespace Jbryn\DistanceCalculator\Tests\Service;

use Jbryn\DistanceCalculator\Exception\ValidationException;
use Jbryn\DistanceCalculator\Service\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ValidatorTest extends TestCase
{
    private const VALID = ['lat1' => 51.5074, 'lon1' => -0.1278, 'lat2' => 48.8566, 'lon2' => 2.3522];

    private Validator $validator;

    protected function setUp(): void
    {
        $this->validator = new Validator();
    }

    /**
     * @param array<string, mixed> $data
     */
    #[DataProvider('validPayloads')]
    public function testAcceptsValidPayload(array $data): void
    {
        $coordinates = $this->validator->validate($data);

        $this->assertSame(['lat1', 'lon1', 'lat2', 'lon2'], array_keys($coordinates));
        $this->assertContainsOnly('float', $coordinates);
    }

    /**
     * @return array<array<mixed>>
     */
    public static function validPayloads(): array
    {
        return [
            'floats' => [self::VALID],
            'integers' => [['lat1' => 52, 'lon1' => 21, 'lat2' => 50, 'lon2' => 19]],
            'numeric strings' => [['lat1' => '51.5074', 'lon1' => '-0.1278', 'lat2' => '48.8566', 'lon2' => '2.3522']],
            'zeros' => [['lat1' => 0, 'lon1' => 0, 'lat2' => 0, 'lon2' => 0]],
            'boundaries' => [['lat1' => -90, 'lon1' => -180, 'lat2' => 90, 'lon2' => 180]],
        ];
    }

    #[DataProvider('nonArrayBodies')]
    public function testRejectsNonArrayBody(mixed $body): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Request body must be a JSON object');

        $this->validator->validate($body);
    }

    /**
     * @return array<array<mixed>>
     */
    public static function nonArrayBodies(): array
    {
        return [
            'null' => [null],
            'string' => ['lat1=1'],
            'object' => [new \stdClass()],
        ];
    }

    #[DataProvider('requiredFields')]
    public function testRejectsMissingField(string $field): void
    {
        $data = self::VALID;
        unset($data[$field]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Missing required field: {$field}");

        $this->validator->validate($data);
    }

    #[DataProvider('requiredFields')]
    public function testRejectsNullField(string $field): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Missing required field: {$field}");

        $this->validator->validate([...self::VALID, $field => null]);
    }

    /**
     * @return array<array<mixed>>
     */
    public static function requiredFields(): array
    {
        return [['lat1'], ['lon1'], ['lat2'], ['lon2']];
    }

    #[DataProvider('nonNumericValues')]
    public function testRejectsNonNumericValue(mixed $value): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Field lat1 must be a number');

        $this->validator->validate([...self::VALID, 'lat1' => $value]);
    }

    /**
     * @return array<array<mixed>>
     */
    public static function nonNumericValues(): array
    {
        return [
            'text' => ['abc'],
            'empty string' => [''],
            'boolean' => [true],
            'array' => [[51.5]],
        ];
    }

    #[DataProvider('outOfRangeLatitudes')]
    public function testRejectsOutOfRangeLatitude(string $field, float $value): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Invalid latitude for {$field}. Value must be between -90 and 90 degrees");

        $this->validator->validate([...self::VALID, $field => $value]);
    }

    /**
     * @return array<array<mixed>>
     */
    public static function outOfRangeLatitudes(): array
    {
        return [
            'lat1 too high' => ['lat1', 90.0001],
            'lat1 too low' => ['lat1', -90.0001],
            'lat2 too high' => ['lat2', 90.0001],
            'lat2 too low' => ['lat2', -90.0001],
        ];
    }

    #[DataProvider('outOfRangeLongitudes')]
    public function testRejectsOutOfRangeLongitude(string $field, float $value): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Invalid longitude for {$field}. Value must be between -180 and 180 degrees");

        $this->validator->validate([...self::VALID, $field => $value]);
    }

    /**
     * @return array<array<mixed>>
     */
    public static function outOfRangeLongitudes(): array
    {
        return [
            'lon1 too high' => ['lon1', 180.0001],
            'lon1 too low' => ['lon1', -180.0001],
            'lon2 too high' => ['lon2', 180.0001],
            'lon2 too low' => ['lon2', -180.0001],
        ];
    }
}
