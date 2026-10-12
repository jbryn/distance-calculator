<?php

declare(strict_types=1);

namespace Jbryn\DistanceCalculator\Overpass;

use InvalidArgumentException;
use JsonException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Talks to the public Overpass servers over HTTP.
 *
 * The servers are run by volunteers and regularly time out or refuse requests,
 * so each query is tried against every configured server in turn until one answers.
 */
final class HttpOverpassClient implements OverpassClient
{
    /** @var list<string> */
    private readonly array $endpoints;

    /**
     * @param array<string> $endpoints server URLs, in order of preference
     * @param string        $userAgent identifies this application, as the usage policy asks
     */
    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        array $endpoints,
        private readonly string $userAgent,
    ) {
        if ($endpoints === []) {
            throw new InvalidArgumentException('At least one Overpass endpoint is required');
        }

        $this->endpoints = array_values($endpoints);
    }

    public function query(string $query): array
    {
        $failures = [];

        foreach ($this->endpoints as $endpoint) {
            try {
                return $this->send($endpoint, $query);
            } catch (OverpassUnavailableException $e) {
                $failures[] = "{$endpoint}: {$e->getMessage()}";
            }
        }

        throw new OverpassUnavailableException('No Overpass server answered. ' . implode('; ', $failures));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function send(string $endpoint, string $query): array
    {
        $request = $this->requestFactory->createRequest('POST', $endpoint)
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withHeader('Accept', 'application/json')
            ->withHeader('User-Agent', $this->userAgent)
            ->withBody($this->streamFactory->createStream(http_build_query(['data' => $query])));

        try {
            $response = $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new OverpassUnavailableException("request failed ({$e->getMessage()})", previous: $e);
        }

        if ($response->getStatusCode() !== 200) {
            throw new OverpassUnavailableException("HTTP {$response->getStatusCode()}");
        }

        try {
            $data = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new OverpassUnavailableException('response was not valid JSON', previous: $e);
        }

        if (!is_array($data) || !isset($data['elements']) || !is_array($data['elements'])) {
            throw new OverpassUnavailableException('response had no "elements" list');
        }

        // A query that runs out of time or memory still answers with HTTP 200,
        // but with incomplete data and an explanation in "remark"
        if (isset($data['remark']) && is_string($data['remark']) && str_contains($data['remark'], 'error')) {
            throw new OverpassUnavailableException("server reported \"{$data['remark']}\"");
        }

        return ElementList::fromMixed($data['elements']);
    }
}
