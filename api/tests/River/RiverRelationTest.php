<?php

declare(strict_types=1);

namespace Jbryn\Downriver\Tests\River;

use Jbryn\Downriver\Geo\Point;
use Jbryn\Downriver\River\InvalidRiverDataException;
use Jbryn\Downriver\River\Piece;
use Jbryn\Downriver\River\RiverRelation;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RiverRelationTest extends TestCase
{
    public function testReadsIdNameAndPieces(): void
    {
        $river = RiverRelation::fromElement(self::relation([
            self::way(10, 'main_stream', [[53.5, 16.5], [53.4, 16.6], [53.3, 16.7]]),
        ]));

        $this->assertSame(8974372, $river->id);
        $this->assertSame('Gwda', $river->name);
        $this->assertCount(1, $river->pieces);
        $this->assertSame(10, $river->pieces[0]->wayId);
        $this->assertEquals(
            [new Point(53.5, 16.5), new Point(53.4, 16.6), new Point(53.3, 16.7)],
            $river->pieces[0]->points,
        );
    }

    public function testKeepsMainStreamAndRolelessWaysInTheListedOrder(): void
    {
        $river = RiverRelation::fromElement(self::relation([
            self::way(3, 'main_stream'),
            self::way(1, ''),
            self::way(2, 'main_stream'),
        ]));

        $this->assertSame([3, 1, 2], self::wayIds($river));
    }

    public function testAWayWithoutARoleKeyCountsAsRoleless(): void
    {
        $way = self::way(1, '');
        unset($way['role']);

        $this->assertSame([1], self::wayIds(RiverRelation::fromElement(self::relation([$way]))));
    }

    public function testLeavesOutSideStreamsAndOtherRoles(): void
    {
        $river = RiverRelation::fromElement(self::relation([
            self::way(1, 'main_stream'),
            self::way(2, 'side_stream'),
            self::way(3, 'inner'),
            self::way(4, 'anabranch'),
        ]));

        $this->assertSame([1], self::wayIds($river));
    }

    public function testLeavesOutMembersThatAreNotWays(): void
    {
        $river = RiverRelation::fromElement(self::relation([
            ['type' => 'node', 'ref' => 5, 'role' => 'spring', 'lat' => 53.5, 'lon' => 16.5],
            ['type' => 'relation', 'ref' => 6, 'role' => 'tributary'],
            self::way(1, 'main_stream'),
            'not a member',
        ]));

        $this->assertSame([1], self::wayIds($river));
    }

    public function testAcceptsWholeNumberCoordinates(): void
    {
        $river = RiverRelation::fromElement(self::relation([self::way(1, '', [[53, 16], [54, 17]])]));

        $this->assertEquals([new Point(53.0, 16.0), new Point(54.0, 17.0)], $river->pieces[0]->points);
    }

    public function testNameIsEmptyWhenTheRelationHasNone(): void
    {
        $element = self::relation([self::way(1, '')]);
        unset($element['tags']);

        $this->assertSame('', RiverRelation::fromElement($element)->name);
    }

    public function testARelationWithoutMainCourseWaysHasNoPieces(): void
    {
        $this->assertSame([], RiverRelation::fromElement(self::relation([self::way(1, 'side_stream')]))->pieces);
    }

    /**
     * @param array<string, mixed> $element
     */
    #[DataProvider('invalidElements')]
    public function testRejectsDataOfTheWrongShape(array $element, string $message): void
    {
        $this->expectException(InvalidRiverDataException::class);
        $this->expectExceptionMessage($message);

        RiverRelation::fromElement($element);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidElements(): array
    {
        $noGeometry = self::way(7, 'main_stream');
        unset($noGeometry['geometry']);

        $noRef = self::way(7, 'main_stream');
        unset($noRef['ref']);

        $nullPoint = self::way(7, 'main_stream');
        $nullPoint['geometry'][1] = null;

        return [
            'a way, not a relation' => [
                ['type' => 'way', 'id' => 1, 'members' => []],
                'Expected a relation with an id and members',
            ],
            'no id' => [
                ['type' => 'relation', 'members' => []],
                'Expected a relation with an id and members',
            ],
            'no members' => [
                ['type' => 'relation', 'id' => 1],
                'Expected a relation with an id and members',
            ],
            'way without an id' => [
                self::relation([$noRef]),
                'Relation 8974372 has a way without an id',
            ],
            'way without geometry' => [
                self::relation([$noGeometry]),
                'Way 7 in relation 8974372 has no geometry; the query must end with "out geom"',
            ],
            'point missing from the geometry' => [
                self::relation([$nullPoint]),
                'Way 7 in relation 8974372 has a point without coordinates',
            ],
            'coordinates as text' => [
                self::relation([['type' => 'way', 'ref' => 7, 'role' => '', 'geometry' => [
                    ['lat' => '53.5', 'lon' => '16.5'],
                    ['lat' => 53.4, 'lon' => 16.6],
                ]]]),
                'Way 7 in relation 8974372 has a point without coordinates',
            ],
            'latitude off the globe' => [
                self::relation([self::way(7, '', [[95.0, 16.5], [53.4, 16.6]])]),
                'Way 7 in relation 8974372: Latitude must be between -90 and 90 degrees, got 95',
            ],
            'a single point' => [
                self::relation([self::way(7, '', [[53.5, 16.5]])]),
                'Way 7 in relation 8974372 has fewer than two points',
            ],
        ];
    }

    public function testQueryAsksForOneRelationWithGeometry(): void
    {
        $this->assertSame(
            '[out:json][timeout:60];relation(8974372);out body geom;',
            RiverRelation::overpassQuery(8974372),
        );
    }

    /**
     * @param list<mixed> $members
     *
     * @return array<string, mixed>
     */
    private static function relation(array $members): array
    {
        return [
            'type' => 'relation',
            'id' => 8974372,
            'tags' => ['name' => 'Gwda', 'type' => 'waterway', 'waterway' => 'river'],
            'members' => $members,
        ];
    }

    /**
     * @param list<array{int|float, int|float}> $coordinates
     *
     * @return array{type: string, ref: int, role: string, geometry: list<array{lat: int|float, lon: int|float}|null>}
     */
    private static function way(int $id, string $role, array $coordinates = [[53.5, 16.5], [53.4, 16.6]]): array
    {
        return [
            'type' => 'way',
            'ref' => $id,
            'role' => $role,
            'geometry' => array_map(
                static fn(array $pair): array => ['lat' => $pair[0], 'lon' => $pair[1]],
                $coordinates,
            ),
        ];
    }

    /**
     * @return list<int>
     */
    private static function wayIds(RiverRelation $river): array
    {
        return array_map(static fn(Piece $piece): int => $piece->wayId, $river->pieces);
    }
}
