<?php

declare(strict_types=1);

namespace Jbryn\Downriver\River;

use RuntimeException;

/**
 * The OpenStreetMap data for a river does not have the expected shape.
 */
final class InvalidRiverDataException extends RuntimeException {}
