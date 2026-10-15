<?php

declare(strict_types=1);

namespace Jbryn\Downriver\River;

/**
 * A river as it appears in search results: enough to list it and to ask for the rest.
 */
final class RiverSummary
{
    /**
     * @param int $id the id of the river's relation in OpenStreetMap
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
    ) {}
}
