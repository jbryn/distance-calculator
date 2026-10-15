<?php

declare(strict_types=1);

namespace Jbryn\Downriver\River;

use Jbryn\Downriver\Geo\Point;

/**
 * A map feature placed on a river.
 */
final class RiverFeature
{
    /**
     * @param Point $point    where the feature meets the river's line
     * @param float $distance meters along the river: from the source for a river's features,
     *                        from the start of the section for a section's features
     */
    public function __construct(
        public readonly FeatureKind $kind,
        public readonly ?string $name,
        public readonly Point $point,
        public readonly float $distance,
    ) {}

    public function at(float $distance): self
    {
        return new self($this->kind, $this->name, $this->point, $distance);
    }
}
