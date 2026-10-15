<?php

declare(strict_types=1);

namespace Jbryn\Downriver\Tests\River;

use Jbryn\Downriver\Geo\Point;
use Jbryn\Downriver\River\FeatureKind;
use Jbryn\Downriver\River\InvalidRiverDataException;
use Jbryn\Downriver\River\MapFeature;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MapFeatureTest extends TestCase
{
    /**
     * @param array<string, string> $tags
     */
    #[DataProvider('recognisedTags')]
    public function testRecognisesTheKindFromTheTags(array $tags, FeatureKind $kind, string $category): void
    {
        $feature = MapFeature::fromElement(self::node($tags));

        $this->assertNotNull($feature);
        $this->assertSame($kind, $feature->kind);
        $this->assertSame($category, $feature->kind->category());
    }

    /**
     * @return array<string, array{array<string, string>, FeatureKind, string}>
     */
    public static function recognisedTags(): array
    {
        return [
            'weir' => [['waterway' => 'weir'], FeatureKind::Weir, 'obstacle'],
            'dam' => [['waterway' => 'dam'], FeatureKind::Dam, 'obstacle'],
            'lock gate' => [['waterway' => 'lock_gate'], FeatureKind::Lock, 'obstacle'],
            'lock chamber' => [['waterway' => 'canal', 'lock' => 'yes'], FeatureKind::Lock, 'obstacle'],
            'rapids' => [['waterway' => 'rapids'], FeatureKind::Rapids, 'obstacle'],
            'waterfall' => [['waterway' => 'waterfall'], FeatureKind::Waterfall, 'obstacle'],
            'canoe put-in' => [['canoe' => 'put_in'], FeatureKind::PutIn, 'access'],
            'canoe put-in and egress' => [['canoe' => 'put_in;egress'], FeatureKind::PutIn, 'access'],
            'whitewater egress' => [['whitewater' => 'egress'], FeatureKind::PutIn, 'access'],
            'access point' => [['waterway' => 'access_point'], FeatureKind::PutIn, 'access'],
            'slipway' => [['leisure' => 'slipway'], FeatureKind::Slipway, 'access'],
            'road bridge' => [['highway' => 'tertiary', 'bridge' => 'yes'], FeatureKind::Bridge, 'access'],
            'footbridge of a special type' => [['highway' => 'footway', 'bridge' => 'viaduct'], FeatureKind::Bridge, 'access'],
            'a weir that carries a road counts as a weir' => [
                ['waterway' => 'weir', 'highway' => 'service', 'bridge' => 'yes'],
                FeatureKind::Weir,
                'obstacle',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $element
     */
    #[DataProvider('ignoredElements')]
    public function testIgnoresElementsThatAreNotFeatures(array $element): void
    {
        $this->assertNull(MapFeature::fromElement($element));
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function ignoredElements(): array
    {
        return [
            'no tags' => [['type' => 'node', 'id' => 1, 'lat' => 53.0, 'lon' => 16.0]],
            'unrelated tags' => [self::node(['amenity' => 'bench'])],
            'the river itself' => [self::node(['waterway' => 'river'])],
            'a road that is not a bridge' => [self::node(['highway' => 'tertiary'])],
            'bridge=no' => [self::node(['highway' => 'tertiary', 'bridge' => 'no'])],
            'planned bridge' => [self::node(['highway' => 'proposed', 'bridge' => 'yes'])],
            'bridge being built' => [self::node(['highway' => 'construction', 'bridge' => 'yes'])],
            'railway bridge' => [self::node(['railway' => 'rail', 'bridge' => 'yes'])],
            'lock=no' => [self::node(['waterway' => 'canal', 'lock' => 'no'])],
            'canoeing allowed, but not a landing place' => [self::node(['canoe' => 'yes'])],
        ];
    }

    public function testANodeIsPlacedAtItsOwnPosition(): void
    {
        $feature = MapFeature::fromElement(self::node(['waterway' => 'weir']));

        $this->assertEquals(new Point(53.4136292, 16.86784), $feature?->point);
    }

    public function testAWayIsPlacedAtItsCenter(): void
    {
        $feature = MapFeature::fromElement([
            'type' => 'way',
            'id' => 30603770,
            'center' => ['lat' => 53.151, 'lon' => 16.738],
            'tags' => ['highway' => 'tertiary', 'bridge' => 'yes'],
        ]);

        $this->assertEquals(new Point(53.151, 16.738), $feature?->point);
    }

    public function testReadsTheName(): void
    {
        $feature = MapFeature::fromElement(self::node(['waterway' => 'weir', 'name' => ' Jaz Koszyce ']));

        $this->assertSame('Jaz Koszyce', $feature?->name);
    }

    public function testABridgePrefersItsOwnNameToTheNameOfTheRoad(): void
    {
        $bridge = ['highway' => 'tertiary', 'bridge' => 'yes', 'name' => 'Pilska'];

        $this->assertSame('Pilska', MapFeature::fromElement(self::node($bridge))?->name);
        $this->assertSame(
            'Most Chrobrego',
            MapFeature::fromElement(self::node($bridge + ['bridge:name' => 'Most Chrobrego']))?->name,
        );
    }

    public function testNameIsNullWhenMissingOrBlank(): void
    {
        $this->assertNull(MapFeature::fromElement(self::node(['waterway' => 'weir']))?->name);
        $this->assertNull(MapFeature::fromElement(self::node(['waterway' => 'weir', 'name' => '  ']))?->name);
    }

    /**
     * @param array<string, mixed> $element
     */
    #[DataProvider('elementsWithoutAPosition')]
    public function testRejectsAFeatureWithoutAUsablePosition(array $element, string $message): void
    {
        $this->expectException(InvalidRiverDataException::class);
        $this->expectExceptionMessage($message);

        MapFeature::fromElement($element);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function elementsWithoutAPosition(): array
    {
        $tags = ['waterway' => 'weir'];

        return [
            'way without a center' => [
                ['type' => 'way', 'id' => 1, 'tags' => $tags],
                'A map feature has no coordinates; the query must end with "out center"',
            ],
            'coordinates as text' => [
                ['type' => 'node', 'id' => 1, 'lat' => '53.4', 'lon' => '16.8', 'tags' => $tags],
                'A map feature has no coordinates',
            ],
            'latitude off the globe' => [
                ['type' => 'node', 'id' => 1, 'lat' => 153.4, 'lon' => 16.8, 'tags' => $tags],
                'A map feature has an impossible position: Latitude must be between -90 and 90 degrees',
            ],
        ];
    }

    public function testQueryAsksForFeaturesAroundTheWaysOfTheRelation(): void
    {
        $query = MapFeature::overpassQuery(8974372);

        $this->assertStringContainsString('relation(8974372);', $query);
        $this->assertStringContainsString('way(r)->.river;', $query);
        $this->assertStringContainsString('out tags center;', $query);
        $this->assertStringStartsWith('[out:json]', $query);
    }

    #[DataProvider('limits')]
    public function testEachKindHasItsOwnLimits(FeatureKind $kind, float $maxOffset, float $mergeWithin): void
    {
        $this->assertSame($maxOffset, $kind->maxOffset());
        $this->assertSame($mergeWithin, $kind->mergeWithin());
    }

    /**
     * @return array<string, array{FeatureKind, float, float}>
     */
    public static function limits(): array
    {
        return [
            'weir' => [FeatureKind::Weir, 60.0, 30.0],
            'dam' => [FeatureKind::Dam, 250.0, 100.0],
            'lock' => [FeatureKind::Lock, 60.0, 200.0],
            'rapids' => [FeatureKind::Rapids, 60.0, 30.0],
            'waterfall' => [FeatureKind::Waterfall, 60.0, 30.0],
            'put-in' => [FeatureKind::PutIn, 150.0, 30.0],
            'slipway' => [FeatureKind::Slipway, 150.0, 30.0],
            'bridge' => [FeatureKind::Bridge, 60.0, 30.0],
        ];
    }

    /**
     * @param array<string, string> $tags
     *
     * @return array<string, mixed>
     */
    private static function node(array $tags): array
    {
        return ['type' => 'node', 'id' => 1880977781, 'lat' => 53.4136292, 'lon' => 16.86784, 'tags' => $tags];
    }
}
