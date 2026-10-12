<?php

declare(strict_types=1);

namespace Jbryn\DistanceCalculator\Tests\Overpass;

use Jbryn\DistanceCalculator\Overpass\OverpassClient;
use Jbryn\DistanceCalculator\Overpass\OverpassUnavailableException;

/**
 * Stands in for the network-backed client: returns a fixed answer or fails, and counts calls.
 */
final class FakeOverpassClient implements OverpassClient
{
    /** @var list<string> */
    public array $queries = [];

    public bool $available = true;

    /**
     * @param list<array<string, mixed>> $elements
     */
    public function __construct(public array $elements = []) {}

    public function query(string $query): array
    {
        $this->queries[] = $query;

        if (!$this->available) {
            throw new OverpassUnavailableException('fake outage');
        }

        return $this->elements;
    }
}
