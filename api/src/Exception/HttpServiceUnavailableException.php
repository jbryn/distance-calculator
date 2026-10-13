<?php

declare(strict_types=1);

namespace Jbryn\DistanceCalculator\Exception;

use Slim\Exception\HttpSpecializedException;

/**
 * Something the application depends on is missing or broken (HTTP 503).
 */
final class HttpServiceUnavailableException extends HttpSpecializedException
{
    /** @var int */
    protected $code = 503;

    /** @var string */
    protected $message = 'Service unavailable.';

    protected string $title = '503 Service Unavailable';

    protected string $description = 'The server cannot handle the request right now.';
}
