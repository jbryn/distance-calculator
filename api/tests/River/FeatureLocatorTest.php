<?php

declare(strict_types=1);

namespace Jbryn\Downriver\Tests\River;

use Jbryn\Downriver\Geo\Point;
use Jbryn\Downriver\Geo\Polyline;
use Jbryn\Downriver\River\FeatureKind;
use Jbryn\Downriver\River\FeatureLocator;
use Jbryn\Downriver\River\MapFeature;
use Jbryn\Downriver\River\RiverFeature;
use PHPUnit\Framework\TestCase;

/**
 * The invented river flows east along the equator from longitude 0 to 3. There, one
 * degree is DEGREE meters in either direction, so 0.0001 degrees is 11.1 m.
 */
class FeatureLocatorTest extends TestCase
{
    /** Length of one degree of arc: 111,194.93 m */
    private const DEGREE = M_PI / 180 * Point::EARTH_RADIUS_METERS;

    /** Tolerance in meters */
    private const DELTA = 0.01;

    public function testNoFeaturesGivesAnEmptyList(): void
    {
        $this->assertSame([], self::locate());
    }

    public function testPlacesAFeatureByItsDistanceFromTheSource(): void
    {
        $features = self::locate(new MapFeature(FeatureKind::Weir, 'Jaz', new Point(0, 1.5)));

        $this->assertCount(1, $features);
        $this->assertSame(FeatureKind::Weir, $features[0]->kind);
        $this->assertSame('Jaz', $features[0]->name);
        $this->assertEqualsWithDelta(1.5 * self::DEGREE, $features[0]->distance, self::DELTA);
    }

    public function testOrdersFeaturesFromSourceToMouth(): void
    {
        $features = self::locate(
            new MapFeature(FeatureKind::Bridge, 'third', new Point(0, 2.5)),
            new MapFeature(FeatureKind::Weir, 'first', new Point(0, 0.5)),
            new MapFeature(FeatureKind::PutIn, 'second', new Point(0, 1.5)),
        );

        $this->assertSame(['first', 'second', 'third'], self::names($features));
    }

    public function testAFeatureBesideTheRiverIsMovedOntoIt(): void
    {
        // 0.0004 degrees north of the river is 44.5 m
        $features = self::locate(new MapFeature(FeatureKind::Weir, null, new Point(0.0004, 1.5)));

        $this->assertCount(1, $features);
        $this->assertEqualsWithDelta(0.0, $features[0]->point->latitude, 1e-9);
        $this->assertEqualsWithDelta(1.5, $features[0]->point->longitude, 1e-9);
        $this->assertEqualsWithDelta(1.5 * self::DEGREE, $features[0]->distance, self::DELTA);
    }

    public function testLeavesOutAFeatureTooFarFromTheRiver(): void
    {
        // 0.0005 degrees is 55.6 m, inside the 60 m allowed for a weir; 0.0006 degrees is 66.7 m
        $features = self::locate(
            new MapFeature(FeatureKind::Weir, 'near', new Point(0.0005, 1)),
            new MapFeature(FeatureKind::Weir, 'far', new Point(0.0006, 2)),
        );

        $this->assertSame(['near'], self::names($features));
    }

    public function testHowFarIsTooFarDependsOnTheKind(): void
    {
        // 0.001 degrees is 111 m: too far for a bridge, fine for a landing place or a dam
        $beside = new Point(0.001, 1);

        $features = self::locate(
            new MapFeature(FeatureKind::Bridge, 'bridge', $beside),
            new MapFeature(FeatureKind::PutIn, 'put-in', $beside),
            new MapFeature(FeatureKind::Dam, 'dam', $beside),
        );

        $this->assertEqualsCanonicalizing(['put-in', 'dam'], self::names($features));
    }

    public function testMergesTheSameKindMappedTwiceInOnePlace(): void
    {
        // Two carriageways of one bridge, 0.0002 degrees (22 m) apart
        $features = self::locate(
            new MapFeature(FeatureKind::Bridge, null, new Point(0, 1.0000)),
            new MapFeature(FeatureKind::Bridge, null, new Point(0, 1.0002)),
        );

        $this->assertCount(1, $features);
        $this->assertEqualsWithDelta(self::DEGREE, $features[0]->distance, self::DELTA);
    }

    public function testAMergedPlaceTakesItsNameFromWhicheverCopyHasOne(): void
    {
        $features = self::locate(
            new MapFeature(FeatureKind::Bridge, null, new Point(0, 1.0000)),
            new MapFeature(FeatureKind::Bridge, 'Most Chrobrego', new Point(0, 1.0001)),
            new MapFeature(FeatureKind::Bridge, 'Kładka', new Point(0, 1.0002)),
        );

        $this->assertSame(['Most Chrobrego'], self::names($features));
        $this->assertEqualsWithDelta(self::DEGREE, $features[0]->distance, self::DELTA);
    }

    public function testDoesNotMergeTheSameKindFartherApart(): void
    {
        // 0.0003 degrees is 33 m, just over the 30 m within which bridges are merged
        $features = self::locate(
            new MapFeature(FeatureKind::Bridge, 'a', new Point(0, 1.0000)),
            new MapFeature(FeatureKind::Bridge, 'b', new Point(0, 1.0003)),
        );

        $this->assertSame(['a', 'b'], self::names($features));
    }

    public function testDoesNotMergeDifferentKindsInOnePlace(): void
    {
        $features = self::locate(
            new MapFeature(FeatureKind::Weir, 'weir', new Point(0, 1)),
            new MapFeature(FeatureKind::Bridge, 'bridge over the weir', new Point(0, 1)),
        );

        $this->assertCount(2, $features);
    }

    public function testMergesAcrossAFeatureOfAnotherKindLyingBetween(): void
    {
        $features = self::locate(
            new MapFeature(FeatureKind::Bridge, null, new Point(0, 1.0000)),
            new MapFeature(FeatureKind::Weir, null, new Point(0, 1.0001)),
            new MapFeature(FeatureKind::Bridge, null, new Point(0, 1.0002)),
        );

        $this->assertSame(
            [FeatureKind::Bridge, FeatureKind::Weir],
            array_map(static fn(RiverFeature $feature): FeatureKind => $feature->kind, $features),
        );
    }

    public function testTheGatesAtEitherEndOfALockAreOneLock(): void
    {
        // 0.0015 degrees is 167 m: one lock, but it would be two bridges
        $features = self::locate(
            new MapFeature(FeatureKind::Lock, null, new Point(0, 1.0000)),
            new MapFeature(FeatureKind::Lock, null, new Point(0, 1.0015)),
            new MapFeature(FeatureKind::Bridge, null, new Point(0, 2.0000)),
            new MapFeature(FeatureKind::Bridge, null, new Point(0, 2.0015)),
        );

        $this->assertSame(
            [FeatureKind::Lock, FeatureKind::Bridge, FeatureKind::Bridge],
            array_map(static fn(RiverFeature $feature): FeatureKind => $feature->kind, $features),
        );
    }

    public function testAChainOfCloseFeaturesIsMeasuredFromTheFirst(): void
    {
        // Each is 22 m from the next, but the third is 44 m from the first, so it is a place of its own
        $features = self::locate(
            new MapFeature(FeatureKind::Bridge, 'a', new Point(0, 1.0000)),
            new MapFeature(FeatureKind::Bridge, 'b', new Point(0, 1.0002)),
            new MapFeature(FeatureKind::Bridge, 'c', new Point(0, 1.0004)),
        );

        $this->assertSame(['a', 'c'], self::names($features));
    }

    /**
     * @return list<RiverFeature>
     */
    private static function locate(MapFeature ...$features): array
    {
        $line = new Polyline([new Point(0, 0), new Point(0, 1), new Point(0, 2), new Point(0, 3)]);

        return (new FeatureLocator())->locate($line, $features);
    }

    /**
     * @param list<RiverFeature> $features
     *
     * @return list<string|null>
     */
    private static function names(array $features): array
    {
        return array_map(static fn(RiverFeature $feature): ?string => $feature->name, $features);
    }
}
