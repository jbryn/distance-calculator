<?php

declare(strict_types=1);

namespace Jbryn\DistanceCalculator\Overpass;

/**
 * Checks that decoded JSON has the shape of an Overpass "elements" list.
 */
final class ElementList
{
    /**
     * @param array<mixed> $elements
     *
     * @return list<array<string, mixed>>
     *
     * @throws OverpassUnavailableException when an entry is not a JSON object
     */
    public static function fromMixed(array $elements): array
    {
        $checked = [];

        foreach ($elements as $element) {
            if (!is_array($element)) {
                throw new OverpassUnavailableException('response contained an element that is not an object');
            }

            $object = [];
            foreach ($element as $key => $value) {
                if (!is_string($key)) {
                    throw new OverpassUnavailableException('response contained an element that is not an object');
                }

                $object[$key] = $value;
            }

            $checked[] = $object;
        }

        return $checked;
    }
}
