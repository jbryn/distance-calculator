<?php

declare(strict_types=1);

namespace Jbryn\Downriver\Tests\River;

use Jbryn\Downriver\Config\Settings;
use Jbryn\Downriver\Overpass\OverpassClient;
use Jbryn\Downriver\Overpass\OverpassUnavailableException;
use Jbryn\Downriver\River\FeatureKind;
use Jbryn\Downriver\River\FeatureLocator;
use Jbryn\Downriver\River\MapFeature;
use Jbryn\Downriver\River\RiverCatalogue;
use Jbryn\Downriver\River\RiverFeature;
use Jbryn\Downriver\River\RiverNotFoundException;
use Jbryn\Downriver\River\RiverRelation;
use Jbryn\Downriver\River\RiverRepository;
use Jbryn\Downriver\River\RiverStitcher;
use Jbryn\Downriver\River\RiverSummary;
use Jbryn\Downriver\River\RiverUnavailableException;
use Jbryn\Downriver\Tests\Overpass\FakeOverpassClient;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RiverRepositoryTest extends TestCase
{
    private const GWDA = 8974372;
    private const SOLA = 545358;

    public function testBuildsARiverFromItsSavedMapData(): void
    {
        $river = self::repository(RiverFixtures::client())->get(self::GWDA);

        $this->assertSame(self::GWDA, $river->id);
        $this->assertEqualsWithDelta(142.93, $river->line->length() / 1000, 0.01);
    }

    public function testTheNameComesFromTheCatalogueNotFromTheMap(): void
    {
        $this->assertSame('Gwda (catalogue)', self::repository(RiverFixtures::client())->get(self::GWDA)->name);
    }

    public function testAsksOverpassForTheCourseAndThenForTheFeatures(): void
    {
        $overpass = new FakeOverpassClient([self::relation()]);

        self::repository($overpass)->get(self::GWDA);

        $this->assertSame(
            [RiverRelation::overpassQuery(self::GWDA), MapFeature::overpassQuery(self::GWDA)],
            $overpass->queries,
        );
    }

    public function testPlacesTheFeaturesOnTheRiver(): void
    {
        // The river runs from (53.5, 16.5) to (53.4, 16.6); the weir is at its midpoint, the bridge at its end
        $river = self::repository(self::answering([self::relation()], [
            ['type' => 'way', 'id' => 7, 'center' => ['lat' => 53.4, 'lon' => 16.6], 'tags' => ['highway' => 'track', 'bridge' => 'yes']],
            ['type' => 'node', 'id' => 8, 'lat' => 53.45, 'lon' => 16.55, 'tags' => ['waterway' => 'weir', 'name' => 'Jaz']],
            ['type' => 'node', 'id' => 9, 'lat' => 53.45, 'lon' => 16.55, 'tags' => ['amenity' => 'bench']],
        ]))->get(self::GWDA);

        $this->assertCount(2, $river->features);

        $this->assertSame(FeatureKind::Weir, $river->features[0]->kind);
        $this->assertSame('Jaz', $river->features[0]->name);
        $this->assertEqualsWithDelta($river->line->length() / 2, $river->features[0]->distance, 1.0);

        $this->assertSame(FeatureKind::Bridge, $river->features[1]->kind);
        $this->assertEqualsWithDelta($river->line->length(), $river->features[1]->distance, 0.01);
    }

    public function testARiverWithNothingAlongItHasNoFeatures(): void
    {
        $this->assertSame([], self::repository(self::answering([self::relation()], []))->get(self::GWDA)->features);
    }

    public function testUnavailableWhenTheFeaturesCannotBeObtained(): void
    {
        $this->expectException(RiverUnavailableException::class);
        $this->expectExceptionMessage('No obstacle data for river 8974372: refused');

        self::repository(self::answering([self::relation()], null))->get(self::GWDA);
    }

    public function testUnavailableWhenAFeatureHasNoPosition(): void
    {
        $this->expectException(RiverUnavailableException::class);
        $this->expectExceptionMessage('Unusable obstacle data for river 8974372: A map feature has no coordinates');

        self::repository(self::answering([self::relation()], [
            ['type' => 'way', 'id' => 7, 'tags' => ['waterway' => 'weir']],
        ]))->get(self::GWDA);
    }

    public function testARiverOutsideTheCatalogueIsNotFoundAndNotLookedUp(): void
    {
        $overpass = new FakeOverpassClient([self::relation()]);

        try {
            self::repository($overpass)->get(34392);
            $this->fail('Expected a RiverNotFoundException');
        } catch (RiverNotFoundException $e) {
            $this->assertSame(34392, $e->riverId);
            $this->assertSame([], $overpass->queries);
        }
    }

    public function testUnavailableWhenTheMapDataCannotBeObtained(): void
    {
        $overpass = new FakeOverpassClient();
        $overpass->available = false;

        $this->expectException(RiverUnavailableException::class);
        $this->expectExceptionMessage('No map data for river 8974372: fake outage');

        self::repository($overpass)->get(self::GWDA);
    }

    /**
     * @param list<array<string, mixed>> $elements
     */
    #[DataProvider('unusableResponses')]
    public function testUnavailableWhenTheMapDataIsUnusable(array $elements, string $message): void
    {
        $this->expectException(RiverUnavailableException::class);
        $this->expectExceptionMessage($message);

        self::repository(new FakeOverpassClient($elements))->get(self::GWDA);
    }

    /**
     * @return array<string, array{list<array<string, mixed>>, string}>
     */
    public static function unusableResponses(): array
    {
        return [
            'the relation does not exist' => [[], 'OpenStreetMap has no relation 8974372'],
            'not a relation' => [
                [['type' => 'way', 'id' => 5]],
                'Unusable map data for river 8974372: Expected a relation with an id and members',
            ],
            'only side streams' => [
                [self::relation('side_stream')],
                'Relation 8974372 has no main-course ways',
            ],
        ];
    }

    public function testARiverWithGapsIsUnavailableRatherThanMeasuredWrongly(): void
    {
        $this->expectException(RiverUnavailableException::class);
        $this->expectExceptionMessage('River 545358 has 3 gap(s) in its map data');

        self::repository(RiverFixtures::client())->get(self::SOLA);
    }

    #[DataProvider('shippedRivers')]
    public function testBuildsEveryShippedRiverWithItsFeatures(int $id, int $features, int $obstacles): void
    {
        $river = self::shipped()->get($id);

        $this->assertGreaterThan(100_000, $river->line->length());
        $this->assertCount($features, $river->features);
        $this->assertCount($obstacles, array_filter(
            $river->features,
            static fn(RiverFeature $feature): bool => $feature->kind->category() === 'obstacle',
        ));

        foreach ($river->features as $index => $feature) {
            $this->assertGreaterThanOrEqual(0.0, $feature->distance);
            $this->assertLessThanOrEqual($river->line->length(), $feature->distance);
            $this->assertGreaterThanOrEqual($river->features[max(0, $index - 1)]->distance, $feature->distance);
        }
    }

    /**
     * Relation id, features placed on the river, and how many of them are obstacles.
     *
     * @return array<string, array{int, int, int}>
     */
    public static function shippedRivers(): array
    {
        return [
            'Biała (Tarnowska)' => [19995496, 57, 4],
            'Biebrza' => [6378830, 15, 1],
            'Bóbr' => [1582509, 241, 118],
            'Dunajec' => [2822415, 58, 11],
            'Gwda' => [8974372, 52, 16],
            'Noteć' => [6124894, 115, 34],
            'Odra' => [387605, 207, 55],
            'Pasłęka' => [7581082, 46, 5],
            'Raba' => [2473086, 86, 8],
            'Rega' => [12759496, 80, 18],
            'Warta' => [3272339, 226, 51],
            'Wieprz' => [2297716, 97, 8],
            'Wisła' => [34392, 292, 173],
            'Wisłok' => [2020696, 91, 6],
            'Wisłoka' => [7577712, 55, 10],
        ];
    }

    public function testEveryRiverInTheShippedCatalogueIsInTheTableAbove(): void
    {
        $catalogue = RiverCatalogue::fromFile((new Settings())->riverCatalogueFile);

        $this->assertEqualsCanonicalizing(
            array_map(static fn(RiverSummary $river): string => $river->name, $catalogue->search('')),
            array_keys(self::shippedRivers()),
        );
    }

    private static function shipped(): RiverRepository
    {
        return new RiverRepository(
            RiverCatalogue::fromFile((new Settings())->riverCatalogueFile),
            RiverFixtures::shippedOnlyClient(),
            new RiverStitcher(),
            new FeatureLocator(),
        );
    }

    /**
     * A client that answers the query for the course with one list of elements and the
     * query for the features with another, or refuses a query whose answer is null.
     *
     * @param list<array<string, mixed>>|null $course
     * @param list<array<string, mixed>>|null $features
     */
    private static function answering(?array $course, ?array $features): OverpassClient
    {
        return new class ($course, $features) implements OverpassClient {
            /**
             * @param list<array<string, mixed>>|null $course
             * @param list<array<string, mixed>>|null $features
             */
            public function __construct(private readonly ?array $course, private readonly ?array $features) {}

            public function query(string $query): array
            {
                $answer = str_contains($query, 'out body geom') ? $this->course : $this->features;

                return $answer ?? throw new OverpassUnavailableException('refused');
            }
        };
    }

    private static function repository(OverpassClient $overpass): RiverRepository
    {
        $catalogue = new RiverCatalogue([
            new RiverSummary(self::GWDA, 'Gwda (catalogue)'),
            new RiverSummary(self::SOLA, 'Soła'),
        ]);

        return new RiverRepository($catalogue, $overpass, new RiverStitcher(), new FeatureLocator());
    }

    /**
     * @return array<string, mixed>
     */
    private static function relation(string $role = 'main_stream'): array
    {
        return [
            'type' => 'relation',
            'id' => self::GWDA,
            'tags' => ['name' => 'Gwda'],
            'members' => [[
                'type' => 'way',
                'ref' => 1,
                'role' => $role,
                'geometry' => [['lat' => 53.5, 'lon' => 16.5], ['lat' => 53.4, 'lon' => 16.6]],
            ]],
        ];
    }
}
