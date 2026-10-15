<?php

declare(strict_types=1);

namespace Jbryn\Downriver\River;

use JsonException;
use RuntimeException;

/**
 * The rivers this application offers.
 *
 * The list is curated by hand (data/rivers.json) rather than searched for in
 * OpenStreetMap: every river on it has saved map data that is known to join into
 * one unbroken line.
 */
final class RiverCatalogue
{
    private const WITHOUT_DIACRITICS = [
        'ą' => 'a', 'ć' => 'c', 'ę' => 'e', 'ł' => 'l', 'ń' => 'n',
        'ó' => 'o', 'ś' => 's', 'ź' => 'z', 'ż' => 'z',
    ];

    /** @var array<int, RiverSummary> by id, sorted by name */
    private readonly array $rivers;

    /**
     * @param array<RiverSummary> $rivers
     */
    public function __construct(array $rivers)
    {
        $rivers = array_values($rivers);
        usort($rivers, static fn(RiverSummary $a, RiverSummary $b): int => self::fold($a->name) <=> self::fold($b->name));

        $byId = [];
        foreach ($rivers as $river) {
            $byId[$river->id] = $river;
        }

        $this->rivers = $byId;
    }

    /**
     * @throws RuntimeException when the file is missing or is not a list of {"id": number, "name": text}
     */
    public static function fromFile(string $path): self
    {
        $contents = is_file($path) ? file_get_contents($path) : false;

        if ($contents === false) {
            throw new RuntimeException("Could not read the river catalogue {$path}");
        }

        try {
            $entries = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException("The river catalogue {$path} is not valid JSON: {$e->getMessage()}", 0, $e);
        }

        if (!is_array($entries)) {
            throw new RuntimeException("The river catalogue {$path} must be a list of rivers");
        }

        $rivers = [];

        foreach ($entries as $entry) {
            $id = is_array($entry) ? ($entry['id'] ?? null) : null;
            $name = is_array($entry) ? ($entry['name'] ?? null) : null;

            if (!is_int($id) || !is_string($name) || trim($name) === '') {
                throw new RuntimeException("Every river in the catalogue {$path} needs a numeric \"id\" and a \"name\"");
            }

            $rivers[] = new RiverSummary($id, $name);
        }

        return new self($rivers);
    }

    public function find(int $id): ?RiverSummary
    {
        return $this->rivers[$id] ?? null;
    }

    /**
     * Rivers whose name contains the text, ignoring case and Polish diacritics
     * ("notec" finds "Noteć"). Names that start with the text come first.
     * An empty text returns every river.
     *
     * @return list<RiverSummary>
     */
    public function search(string $text): array
    {
        $text = self::fold($text);

        $startingWith = [];
        $containing = [];

        foreach ($this->rivers as $river) {
            $position = $text === '' ? 0 : mb_strpos(self::fold($river->name), $text);

            if ($position === 0) {
                $startingWith[] = $river;
            } elseif ($position !== false) {
                $containing[] = $river;
            }
        }

        return [...$startingWith, ...$containing];
    }

    private static function fold(string $text): string
    {
        return strtr(mb_strtolower(trim($text)), self::WITHOUT_DIACRITICS);
    }
}
