<?php

declare(strict_types=1);

namespace Jbryn\DistanceCalculator\Controller;

use InvalidArgumentException;
use Jbryn\DistanceCalculator\Exception\HttpServiceUnavailableException;
use Jbryn\DistanceCalculator\Exception\HttpUnprocessableEntityException;
use Jbryn\DistanceCalculator\Geo\LinePosition;
use Jbryn\DistanceCalculator\Geo\Point;
use Jbryn\DistanceCalculator\Geo\Polyline;
use Jbryn\DistanceCalculator\River\PointTooFarException;
use Jbryn\DistanceCalculator\River\River;
use Jbryn\DistanceCalculator\River\RiverCatalogue;
use Jbryn\DistanceCalculator\River\RiverFeature;
use Jbryn\DistanceCalculator\River\RiverNotFoundException;
use Jbryn\DistanceCalculator\River\RiverRepository;
use Jbryn\DistanceCalculator\River\RiverSummary;
use Jbryn\DistanceCalculator\River\RiverUnavailableException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpNotFoundException;

/**
 * Distances are in meters and points are [latitude, longitude] pairs throughout.
 * Errors are thrown as HTTP exceptions, which JsonErrorHandler turns into JSON responses.
 */
final class RiverController
{
    private const NUMBER = '-?\d+(?:\.\d+)?';

    public function __construct(
        private readonly RiverCatalogue $catalogue,
        private readonly RiverRepository $rivers,
    ) {}

    /**
     * GET /api/rivers?q=text
     */
    public function search(Request $request, Response $response): Response
    {
        $text = $request->getQueryParams()['q'] ?? '';

        if (!is_string($text)) {
            throw new HttpBadRequestException($request, "Query parameter 'q' must be text");
        }

        return self::success($response, array_map(
            static fn(RiverSummary $river): array => ['id' => $river->id, 'name' => $river->name],
            $this->catalogue->search($text),
        ));
    }

    /**
     * GET /api/rivers/{id}
     *
     * @param array<string, string> $arguments the placeholders from the route
     */
    public function show(Request $request, Response $response, array $arguments): Response
    {
        $river = $this->river($request, $arguments['id']);

        return self::success($response, [
            'id' => $river->id,
            'name' => $river->name,
            'length' => self::meters($river->line->length()),
            'points' => self::points($river->line),
            'features' => self::features($river->features, 'distanceFromSource'),
        ]);
    }

    /**
     * GET /api/rivers/{id}/section?from=latitude,longitude&to=latitude,longitude
     *
     * @param array<string, string> $arguments the placeholders from the route
     */
    public function section(Request $request, Response $response, array $arguments): Response
    {
        $from = self::point($request, 'from');
        $to = self::point($request, 'to');
        $river = $this->river($request, $arguments['id']);

        try {
            $section = $river->section($from, $to);
        } catch (PointTooFarException $e) {
            throw new HttpUnprocessableEntityException($request, $e->getMessage(), $e);
        }

        return self::success($response, [
            'river' => ['id' => $river->id, 'name' => $river->name],
            'length' => self::meters($section->length()),
            'downstream' => $section->isDownstream(),
            'from' => self::position($section->from),
            'to' => self::position($section->to),
            'points' => self::points($section->line),
            'features' => self::features($section->features, 'distance'),
        ]);
    }

    private function river(Request $request, string $id): River
    {
        try {
            return $this->rivers->get((int) $id);
        } catch (RiverNotFoundException $e) {
            throw new HttpNotFoundException($request, 'River not found', $e);
        } catch (RiverUnavailableException $e) {
            throw new HttpServiceUnavailableException($request, 'The data for this river is unavailable', $e);
        }
    }

    /**
     * Reads a query parameter written as "latitude,longitude".
     */
    private static function point(Request $request, string $parameter): Point
    {
        $value = $request->getQueryParams()[$parameter] ?? null;

        if (!is_string($value) || trim($value) === '') {
            throw new HttpBadRequestException(
                $request,
                "Query parameter '{$parameter}' is required, as latitude,longitude",
            );
        }

        if (preg_match('/^\s*(' . self::NUMBER . ')\s*,\s*(' . self::NUMBER . ')\s*$/', $value, $matches) !== 1) {
            throw new HttpBadRequestException(
                $request,
                "Query parameter '{$parameter}' must be latitude,longitude, for example 53.54,16.95",
            );
        }

        try {
            return new Point((float) $matches[1], (float) $matches[2]);
        } catch (InvalidArgumentException $e) {
            throw new HttpBadRequestException($request, "Query parameter '{$parameter}': {$e->getMessage()}", $e);
        }
    }

    /**
     * @return array{point: array{float, float}, distanceFromSource: float, offset: float}
     */
    private static function position(LinePosition $position): array
    {
        return [
            'point' => self::coordinates($position->point),
            'distanceFromSource' => self::meters($position->distance),
            'offset' => self::meters($position->offset),
        ];
    }

    /**
     * @param list<RiverFeature> $features
     * @param string             $distanceKey what the feature's distance is measured from
     *
     * @return list<array<string, mixed>>
     */
    private static function features(array $features, string $distanceKey): array
    {
        return array_map(
            static fn(RiverFeature $feature): array => [
                'kind' => $feature->kind->value,
                'category' => $feature->kind->category(),
                'name' => $feature->name,
                'point' => self::coordinates($feature->point),
                $distanceKey => self::meters($feature->distance),
            ],
            $features,
        );
    }

    /**
     * @return list<array{float, float}>
     */
    private static function points(Polyline $line): array
    {
        return array_map(self::coordinates(...), $line->points());
    }

    /**
     * OpenStreetMap stores seven decimal places (about a centimeter); more would only be noise.
     *
     * @return array{float, float}
     */
    private static function coordinates(Point $point): array
    {
        return [round($point->latitude, 7), round($point->longitude, 7)];
    }

    private static function meters(float $meters): float
    {
        return round($meters, 1);
    }

    private static function success(Response $response, mixed $data): Response
    {
        $response->getBody()->write(json_encode(['status' => 'success', 'data' => $data], JSON_THROW_ON_ERROR));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
