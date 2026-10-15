<?php

declare(strict_types=1);

namespace Jbryn\Downriver\River;

use InvalidArgumentException;
use Jbryn\Downriver\Geo\Point;

/**
 * A weir, bridge, landing place or similar, as found on the map near a river.
 */
final class MapFeature
{
    public function __construct(
        public readonly FeatureKind $kind,
        public readonly ?string $name,
        public readonly Point $point,
    ) {}

    /**
     * The Overpass query whose answer fromElement() understands: everything of interest
     * on or beside the ways of a river relation, each reduced to one point.
     */
    public static function overpassQuery(int $relationId): string
    {
        return <<<QUERY
            [out:json][timeout:900];
            relation({$relationId});
            way(r)->.river;
            (
              nw(around.river:100)[waterway~"^(weir|dam|lock_gate|rapids|waterfall|access_point)$"];
              way(around.river:100)[lock=yes];
              nw(around.river:150)[canoe~"put_in|egress"];
              nw(around.river:150)[whitewater~"put_in|egress"];
              nw(around.river:150)[leisure=slipway];
              way(around.river:0)[bridge][bridge!=no][highway][highway!~"^(proposed|construction)$"];
            );
            out tags center;
            QUERY;
    }

    /**
     * @param array<string, mixed> $element a node, or a way with its center, from an Overpass response
     *
     * @return self|null null when the element is not a kind of feature this application shows
     *
     * @throws InvalidRiverDataException when the element has no usable position
     */
    public static function fromElement(array $element): ?self
    {
        $tags = $element['tags'] ?? null;

        if (!is_array($tags)) {
            return null;
        }

        $kind = self::kind($tags);

        if ($kind === null) {
            return null;
        }

        $position = is_array($element['center'] ?? null) ? $element['center'] : $element;
        $latitude = $position['lat'] ?? null;
        $longitude = $position['lon'] ?? null;

        if (!(is_int($latitude) || is_float($latitude)) || !(is_int($longitude) || is_float($longitude))) {
            throw new InvalidRiverDataException('A map feature has no coordinates; the query must end with "out center"');
        }

        try {
            $point = new Point((float) $latitude, (float) $longitude);
        } catch (InvalidArgumentException $e) {
            throw new InvalidRiverDataException("A map feature has an impossible position: {$e->getMessage()}", 0, $e);
        }

        return new self($kind, self::name($tags, $kind), $point);
    }

    /**
     * @param array<mixed> $tags
     */
    private static function name(array $tags, FeatureKind $kind): ?string
    {
        // On a bridge, "name" is usually the name of the road; the bridge's own name has its own tag
        $keys = $kind === FeatureKind::Bridge ? ['bridge:name', 'name'] : ['name'];

        foreach ($keys as $key) {
            $name = $tags[$key] ?? null;

            if (is_string($name) && trim($name) !== '') {
                return trim($name);
            }
        }

        return null;
    }

    /**
     * @param array<mixed> $tags
     */
    private static function kind(array $tags): ?FeatureKind
    {
        $waterway = $tags['waterway'] ?? null;

        $kind = match ($waterway) {
            'weir' => FeatureKind::Weir,
            'dam' => FeatureKind::Dam,
            'lock_gate' => FeatureKind::Lock,
            'rapids' => FeatureKind::Rapids,
            'waterfall' => FeatureKind::Waterfall,
            'access_point' => FeatureKind::PutIn,
            default => null,
        };

        if ($kind !== null) {
            return $kind;
        }

        if (($tags['lock'] ?? null) === 'yes') {
            return FeatureKind::Lock;
        }

        foreach (['canoe', 'whitewater'] as $key) {
            $value = $tags[$key] ?? null;

            if (is_string($value) && preg_match('/put_in|egress/', $value) === 1) {
                return FeatureKind::PutIn;
            }
        }

        if (($tags['leisure'] ?? null) === 'slipway') {
            return FeatureKind::Slipway;
        }

        $highway = $tags['highway'] ?? null;
        $bridge = $tags['bridge'] ?? null;

        if (is_string($bridge) && $bridge !== 'no' && is_string($highway) && !in_array($highway, ['proposed', 'construction'], true)) {
            return FeatureKind::Bridge;
        }

        return null;
    }
}
