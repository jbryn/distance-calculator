<?php

declare(strict_types=1);

namespace Jbryn\DistanceCalculator\River;

use Jbryn\DistanceCalculator\Geo\Polyline;

/**
 * The outcome of joining a river's pieces: one line, and an account of what did not fit.
 */
final class StitchedRiver
{
    /**
     * @param Polyline           $line           the course of the river, from source to mouth
     * @param list<int>          $wayIds         the pieces the line is made of, from source to mouth
     * @param list<int>          $reversedWayIds pieces that were drawn against the flow and had to be turned round
     * @param list<LeftoverPart> $leftovers      pieces that are not part of the line
     */
    public function __construct(
        public readonly Polyline $line,
        public readonly array $wayIds,
        public readonly array $reversedWayIds,
        public readonly array $leftovers,
    ) {}

    /**
     * Leftover parts that do not reach the line. Each one means the line is likely to be
     * missing a stretch of the river, so distances along it cannot be trusted.
     *
     * @return list<LeftoverPart>
     */
    public function gaps(): array
    {
        return array_values(array_filter(
            $this->leftovers,
            static fn(LeftoverPart $part): bool => $part->isDetached(),
        ));
    }

    public function isContinuous(): bool
    {
        return $this->gaps() === [];
    }
}
