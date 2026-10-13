<?php

declare(strict_types=1);

namespace Jbryn\DistanceCalculator\River;

use RuntimeException;

/**
 * The river is offered, but its map data is missing or cannot be made into one line.
 */
final class RiverUnavailableException extends RuntimeException {}
