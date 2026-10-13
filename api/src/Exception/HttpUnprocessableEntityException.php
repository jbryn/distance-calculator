<?php

declare(strict_types=1);

namespace Jbryn\DistanceCalculator\Exception;

use Slim\Exception\HttpSpecializedException;

/**
 * The request is well-formed, but what it asks for cannot be done (HTTP 422).
 */
final class HttpUnprocessableEntityException extends HttpSpecializedException
{
    /** @var int */
    protected $code = 422;

    /** @var string */
    protected $message = 'Unprocessable entity.';

    protected string $title = '422 Unprocessable Entity';

    protected string $description = 'The request was understood but could not be processed.';
}
