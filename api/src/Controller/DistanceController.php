<?php

declare(strict_types=1);

namespace Jbryn\DistanceCalculator\Controller;

use Jbryn\DistanceCalculator\Exception\ValidationException;
use Jbryn\DistanceCalculator\Service\DistanceCalculator;
use Jbryn\DistanceCalculator\Service\Validator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class DistanceController
{
    public function __construct(
        private DistanceCalculator $calculator,
        private Validator $validator,
    ) {}

    public function calculate(Request $request, Response $response): Response
    {
        try {
            $coordinates = $this->validator->validate($request->getParsedBody());

            $result = $this->calculator->calculate(
                $coordinates['lat1'],
                $coordinates['lon1'],
                $coordinates['lat2'],
                $coordinates['lon2'],
            );

            $response->getBody()->write(json_encode([
                'status' => 'success',
                'data' => $result,
            ], JSON_THROW_ON_ERROR));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(200);

        } catch (ValidationException $e) {
            $response->getBody()->write(json_encode([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], JSON_THROW_ON_ERROR));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(400);
        }
    }
}
