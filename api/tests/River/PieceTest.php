<?php

declare(strict_types=1);

namespace Jbryn\Downriver\Tests\River;

use InvalidArgumentException;
use Jbryn\Downriver\Geo\Point;
use Jbryn\Downriver\River\Piece;
use PHPUnit\Framework\TestCase;

class PieceTest extends TestCase
{
    public function testKnowsItsEndsAndLength(): void
    {
        $piece = new Piece(42, [new Point(0, 0), new Point(0, 1), new Point(1, 1)]);

        $this->assertSame(42, $piece->wayId);
        $this->assertEquals(new Point(0, 0), $piece->start());
        $this->assertEquals(new Point(1, 1), $piece->end());
        $this->assertEqualsWithDelta(2 * M_PI / 180 * Point::EARTH_RADIUS_METERS, $piece->length, 0.01);
    }

    public function testNeedsAtLeastTwoPoints(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Way 42 needs at least two points');

        new Piece(42, [new Point(0, 0)]);
    }
}
