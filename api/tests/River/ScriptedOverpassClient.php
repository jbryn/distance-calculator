<?php

declare(strict_types=1);

namespace Jbryn\Downriver\Tests\River;

use Jbryn\Downriver\Overpass\OverpassClient;
use Jbryn\Downriver\Overpass\OverpassUnavailableException;
use LogicException;

/**
 * Gives one prepared answer per call, in order: a list of marker words, or null to refuse.
 */
final class ScriptedOverpassClient implements OverpassClient
{
    /** @var list<string> */
    public array $queries = [];

    /**
     * @param list<list<string>|null> $answers
     */
    public function __construct(private array $answers) {}

    public function query(string $query): array
    {
        $this->queries[] = $query;

        if ($this->answers === []) {
            throw new LogicException('The test did not prepare an answer for this call');
        }

        $answer = array_shift($this->answers);

        if ($answer === null) {
            throw new OverpassUnavailableException('refused');
        }

        return array_map(static fn(string $marker): array => ['is' => $marker], $answer);
    }
}
