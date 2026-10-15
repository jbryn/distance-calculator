<?php

declare(strict_types=1);

namespace Jbryn\Downriver\Overpass;

/**
 * Runs queries against OpenStreetMap data through the Overpass API.
 */
interface OverpassClient
{
    /**
     * @param string $query a query in Overpass QL that asks for JSON output
     *
     * @return list<array<string, mixed>> the "elements" of the response: nodes, ways and relations
     *
     * @throws OverpassUnavailableException when no answer could be obtained
     */
    public function query(string $query): array;
}
