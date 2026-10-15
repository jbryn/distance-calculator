<?php

declare(strict_types=1);

namespace Jbryn\Downriver\River;

/**
 * The kinds of places along a river that matter to a paddler.
 */
enum FeatureKind: string
{
    case Weir = 'weir';
    case Dam = 'dam';
    case Lock = 'lock';
    case Rapids = 'rapids';
    case Waterfall = 'waterfall';
    case PutIn = 'put_in';
    case Slipway = 'slipway';
    case Bridge = 'bridge';

    /**
     * "obstacle" for something in the way, "access" for a place to get on or off the water.
     */
    public function category(): string
    {
        return match ($this) {
            self::Weir, self::Dam, self::Lock, self::Rapids, self::Waterfall => 'obstacle',
            self::PutIn, self::Slipway, self::Bridge => 'access',
        };
    }

    /**
     * How far, in meters, a feature of this kind may be from the river's line and still
     * belong to it. Anything farther is on a side channel, a tributary or the bank.
     *
     * Features are placed by a single point. For a dam that is the middle of a structure
     * that can be hundreds of meters long, and landing places sit on the bank, so both
     * get more room than things that cross the channel.
     */
    public function maxOffset(): float
    {
        return match ($this) {
            self::Dam => 250.0,
            self::PutIn, self::Slipway => 150.0,
            default => 60.0,
        };
    }

    /**
     * Features of the same kind closer together than this, in meters along the river,
     * are one place mapped several times: the two carriageways of a road bridge, the
     * gates at either end of a lock, a dam drawn as both a line and a point.
     */
    public function mergeWithin(): float
    {
        return match ($this) {
            self::Lock => 200.0,
            self::Dam => 100.0,
            default => 30.0,
        };
    }
}
