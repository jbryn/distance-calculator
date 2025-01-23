<?php
declare(strict_types=1);

namespace Jbryn\DistanceCalculator\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Jbryn\DistanceCalculator\Service\DistanceCalculator;
use Jbryn\DistanceCalculator\Service\Validator;

class DistanceController
{
    public function __construct(
        private DistanceCalculator $calculator,
        private Validator $validator
    ) {}

    public function calculate(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
                
        $this->validator->validate($data);
                
        $result = $this->calculator->calculate(
            $data['lat1'],
            $data['lon1'],
            $data['lat2'],
            $data['lon2']
        );
        
        $response->getBody()->write(json_encode([
            'status' => 'success',
            'data' => $result
        ]));
        
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    }
}