<?php

declare(strict_types=1);

namespace Jbryn\DistanceCalculator\Tests\River;

use Jbryn\DistanceCalculator\Config\Settings;
use Jbryn\DistanceCalculator\Overpass\OverpassClient;
use Jbryn\DistanceCalculator\River\RiverCatalogue;
use Jbryn\DistanceCalculator\River\RiverNotFoundException;
use Jbryn\DistanceCalculator\River\RiverRelation;
use Jbryn\DistanceCalculator\River\RiverRepository;
use Jbryn\DistanceCalculator\River\RiverStitcher;
use Jbryn\DistanceCalculator\River\RiverSummary;
use Jbryn\DistanceCalculator\River\RiverUnavailableException;
use Jbryn\DistanceCalculator\Tests\Overpass\FakeOverpassClient;
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

    public function testAsksOverpassForTheRelation(): void
    {
        $overpass = new FakeOverpassClient([self::relation()]);

        self::repository($overpass)->get(self::GWDA);

        $this->assertSame([RiverRelation::overpassQuery(self::GWDA)], $overpass->queries);
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

    public function testEveryRiverInTheShippedCatalogueCanBeBuiltFromTheShippedData(): void
    {
        $settings = new Settings();
        $catalogue = RiverCatalogue::fromFile($settings->riverCatalogueFile);
        $repository = new RiverRepository($catalogue, RiverFixtures::shippedOnlyClient(), new RiverStitcher());

        foreach ($catalogue->search('') as $summary) {
            $river = $repository->get($summary->id);

            $this->assertGreaterThan(100_000, $river->line->length(), $summary->name);
        }
    }

    private static function repository(OverpassClient $overpass): RiverRepository
    {
        $catalogue = new RiverCatalogue([
            new RiverSummary(self::GWDA, 'Gwda (catalogue)'),
            new RiverSummary(self::SOLA, 'Soła'),
        ]);

        return new RiverRepository($catalogue, $overpass, new RiverStitcher());
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
