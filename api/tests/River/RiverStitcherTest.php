<?php

declare(strict_types=1);

namespace Jbryn\Downriver\Tests\River;

use InvalidArgumentException;
use Jbryn\Downriver\Geo\Point;
use Jbryn\Downriver\River\Piece;
use Jbryn\Downriver\River\RiverStitcher;
use Jbryn\Downriver\River\StitchedRiver;
use PHPUnit\Framework\TestCase;

/**
 * The invented rivers here flow east along the equator, where one degree is exactly
 * DEGREE meters. A piece is written as its [latitude, longitude] points in drawing order.
 */
class RiverStitcherTest extends TestCase
{
    /** Length of one degree of arc: 111,194.93 m */
    private const DEGREE = M_PI / 180 * Point::EARTH_RADIUS_METERS;

    /** Tolerance in meters */
    private const DELTA = 0.01;

    public function testNeedsAtLeastOnePiece(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A river needs at least one piece');

        (new RiverStitcher())->stitch([]);
    }

    public function testASinglePieceIsTheWholeRiver(): void
    {
        $river = self::stitch(self::piece(1, [0, 0], [0, 1], [0, 2]));

        $this->assertSame([[0.0, 0.0], [0.0, 1.0], [0.0, 2.0]], self::coordinates($river));
        $this->assertSame([1], $river->wayIds);
        $this->assertSame([], $river->reversedWayIds);
        $this->assertSame([], $river->leftovers);
        $this->assertTrue($river->isContinuous());
    }

    public function testJoinsPiecesWithoutRepeatingTheSharedPoint(): void
    {
        $river = self::stitch(
            self::piece(1, [0, 0], [0, 1]),
            self::piece(2, [0, 1], [0, 2], [0, 3]),
            self::piece(3, [0, 3], [0, 4]),
        );

        $this->assertSame([[0.0, 0.0], [0.0, 1.0], [0.0, 2.0], [0.0, 3.0], [0.0, 4.0]], self::coordinates($river));
        $this->assertSame([1, 2, 3], $river->wayIds);
        $this->assertEqualsWithDelta(4 * self::DEGREE, $river->line->length(), self::DELTA);
    }

    public function testTheOrderOfThePiecesDoesNotMatter(): void
    {
        $river = self::stitch(
            self::piece(3, [0, 3], [0, 4]),
            self::piece(1, [0, 0], [0, 1]),
            self::piece(4, [0, 4], [0, 5]),
            self::piece(2, [0, 1], [0, 2], [0, 3]),
        );

        $this->assertSame([1, 2, 3, 4], $river->wayIds);
        $this->assertSame(
            [[0.0, 0.0], [0.0, 1.0], [0.0, 2.0], [0.0, 3.0], [0.0, 4.0], [0.0, 5.0]],
            self::coordinates($river),
        );
    }

    public function testTurnsRoundAPieceDrawnAgainstTheFlow(): void
    {
        $river = self::stitch(
            self::piece(1, [0, 0], [0, 1]),
            self::piece(2, [0, 3], [0, 2], [0, 1]),
            self::piece(3, [0, 3], [0, 6]),
        );

        $this->assertSame([[0.0, 0.0], [0.0, 1.0], [0.0, 2.0], [0.0, 3.0], [0.0, 6.0]], self::coordinates($river));
        $this->assertSame([1, 2, 3], $river->wayIds);
        $this->assertSame([2], $river->reversedWayIds);
    }

    public function testRunsTheWayThePiecesWereDrawn(): void
    {
        // The same river as above, but every piece is drawn from east to west
        $river = self::stitch(
            self::piece(1, [0, 1], [0, 0]),
            self::piece(2, [0, 3], [0, 2], [0, 1]),
            self::piece(3, [0, 4], [0, 3]),
        );

        $this->assertSame([[0.0, 4.0], [0.0, 3.0], [0.0, 2.0], [0.0, 1.0], [0.0, 0.0]], self::coordinates($river));
        $this->assertSame([3, 2, 1], $river->wayIds);
        $this->assertSame([], $river->reversedWayIds);
    }

    public function testDirectionIsDecidedByLengthNotByNumberOfPieces(): void
    {
        // Two short pieces drawn eastwards, one long piece drawn westwards
        $river = self::stitch(
            self::piece(1, [0, 0], [0, 1]),
            self::piece(2, [0, 1], [0, 2]),
            self::piece(3, [0, 5], [0, 2]),
        );

        $this->assertSame([3, 2, 1], $river->wayIds);
        $this->assertSame([2, 1], $river->reversedWayIds);
        $this->assertSame([0.0, 5.0], self::coordinates($river)[0]);
    }

    public function testLeavesAShorterBranchOutOfTheLine(): void
    {
        // A tributary-like branch joins at longitude 2
        $river = self::stitch(
            self::piece(1, [0, 0], [0, 2]),
            self::piece(2, [0, 2], [0, 4]),
            self::piece(9, [0.5, 2], [0, 2]),
        );

        $this->assertSame([1, 2], $river->wayIds);
        $this->assertEqualsWithDelta(4 * self::DEGREE, $river->line->length(), self::DELTA);

        $this->assertCount(1, $river->leftovers);
        $this->assertSame([9], $river->leftovers[0]->wayIds);
        $this->assertEqualsWithDelta(0.5 * self::DEGREE, $river->leftovers[0]->length, self::DELTA);
        $this->assertEqualsWithDelta(0.0, $river->leftovers[0]->distanceToLine, self::DELTA);
        $this->assertFalse($river->leftovers[0]->isDetached());
        $this->assertTrue($river->isContinuous());
    }

    public function testFollowsTheLongerBranchAtTheSource(): void
    {
        $river = self::stitch(
            self::piece(1, [0, 0], [0, 2]),
            self::piece(2, [0, 2], [0, 6]),
            self::piece(9, [3, 2], [0, 2]),
        );

        $this->assertSame([9, 2], $river->wayIds);
        $this->assertSame([1], $river->leftovers[0]->wayIds);
    }

    public function testTakesTheShorterChannelRoundAnIsland(): void
    {
        $river = self::stitch(
            self::piece(1, [0, 0], [0, 1]),
            self::piece(2, [0, 1], [0, 2]),
            self::piece(8, [0, 1], [0.5, 1.5], [0, 2]),
            self::piece(3, [0, 2], [0, 3]),
        );

        $this->assertSame([1, 2, 3], $river->wayIds);
        $this->assertSame([8], $river->leftovers[0]->wayIds);
        $this->assertTrue($river->isContinuous());
    }

    public function testABranchThatJoinsInTheMiddleOfAPieceIsNotAGap(): void
    {
        // Piece 9 ends on a point of piece 1 that is not one of its ends
        $river = self::stitch(
            self::piece(1, [0, 0], [0, 1], [0, 2]),
            self::piece(9, [0.3, 1], [0, 1]),
        );

        $this->assertSame([1], $river->wayIds);
        $this->assertSame([9], $river->leftovers[0]->wayIds);
        $this->assertEqualsWithDelta(0.0, $river->leftovers[0]->distanceToLine, self::DELTA);
        $this->assertTrue($river->isContinuous());
    }

    public function testReportsAGapInsteadOfMeasuringAcrossIt(): void
    {
        // Nothing connects longitude 3 to longitude 3.1
        $river = self::stitch(
            self::piece(1, [0, 0], [0, 2]),
            self::piece(2, [0, 2], [0, 3]),
            self::piece(3, [0, 3.1], [0, 4]),
            self::piece(4, [0, 4], [0, 5]),
        );

        $this->assertSame([1, 2], $river->wayIds);
        $this->assertEqualsWithDelta(3 * self::DEGREE, $river->line->length(), self::DELTA);

        $this->assertFalse($river->isContinuous());
        $this->assertCount(1, $river->gaps());

        $gap = $river->gaps()[0];
        $this->assertEqualsCanonicalizing([3, 4], $gap->wayIds);
        $this->assertEqualsWithDelta(1.9 * self::DEGREE, $gap->length, self::DELTA);
        $this->assertEqualsWithDelta(0.1 * self::DEGREE, $gap->distanceToLine, self::DELTA);
        $this->assertTrue($gap->isDetached());
    }

    public function testUsesTheLongestOfTheDisconnectedParts(): void
    {
        $river = self::stitch(
            self::piece(1, [0, 0], [0, 1]),
            self::piece(2, [0, 1.1], [0, 3]),
            self::piece(3, [0, 3], [0, 5]),
        );

        $this->assertSame([2, 3], $river->wayIds);
        $this->assertSame([1], $river->gaps()[0]->wayIds);
    }

    public function testGapsListOnlyLeftoversThatDoNotTouchTheLine(): void
    {
        $river = self::stitch(
            self::piece(1, [0, 0], [0, 2]),
            self::piece(2, [0, 2], [0, 4]),
            self::piece(8, [0.5, 2], [0, 2]),
            self::piece(9, [0, 4.2], [0, 5]),
        );

        $this->assertCount(2, $river->leftovers);
        $this->assertCount(1, $river->gaps());
        $this->assertSame([9], $river->gaps()[0]->wayIds);
    }

    public function testALeftoverJustUnderOneMeterAwayCountsAsTouching(): void
    {
        // 0.000008 degrees of longitude on the equator is 0.89 m; 0.00001 is 1.11 m
        $near = self::stitch(self::piece(1, [0, 0], [0, 2]), self::piece(9, [0, 2.000008], [0, 2.5]));
        $far = self::stitch(self::piece(1, [0, 0], [0, 2]), self::piece(9, [0, 2.00001], [0, 2.5]));

        $this->assertTrue($near->isContinuous());
        $this->assertFalse($far->isContinuous());
    }

    public function testARiverThatIsOnlyAClosedLoopFallsBackToTheLongestPiece(): void
    {
        $river = self::stitch(
            self::piece(1, [0, 0], [0.1, 0.1], [0, 0]),
            self::piece(2, [5, 5], [5, 6], [6, 6], [5, 5]),
        );

        $this->assertSame([2], $river->wayIds);
        $this->assertSame([[5.0, 5.0], [5.0, 6.0], [6.0, 6.0], [5.0, 5.0]], self::coordinates($river));
        $this->assertSame([1], $river->leftovers[0]->wayIds);
    }

    public function testAClosedLoopOnTheLineIsLeftOver(): void
    {
        $river = self::stitch(
            self::piece(1, [0, 0], [0, 1]),
            self::piece(7, [0, 1], [0.2, 1.2], [0.2, 0.8], [0, 1]),
            self::piece(2, [0, 1], [0, 2]),
        );

        $this->assertSame([1, 2], $river->wayIds);
        $this->assertSame([7], $river->leftovers[0]->wayIds);
        $this->assertTrue($river->isContinuous());
    }

    private static function stitch(Piece ...$pieces): StitchedRiver
    {
        return (new RiverStitcher())->stitch($pieces);
    }

    /**
     * @param array{int|float, int|float} ...$coordinates
     */
    private static function piece(int $wayId, array ...$coordinates): Piece
    {
        return new Piece($wayId, array_map(
            static fn(array $pair): Point => new Point((float) $pair[0], (float) $pair[1]),
            $coordinates,
        ));
    }

    /**
     * @return list<array{float, float}>
     */
    private static function coordinates(StitchedRiver $river): array
    {
        return array_map(
            static fn(Point $point): array => [$point->latitude, $point->longitude],
            $river->line->points(),
        );
    }
}
