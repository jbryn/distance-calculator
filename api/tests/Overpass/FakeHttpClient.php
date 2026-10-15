<?php

declare(strict_types=1);

namespace Jbryn\Downriver\Tests\Overpass;

use GuzzleHttp\Psr7\Response;
use LogicException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * Stands in for a real HTTP client: replays queued outcomes and records what was sent.
 */
final class FakeHttpClient implements ClientInterface
{
    /** @var list<RequestInterface> */
    public array $requests = [];

    /** @var list<ResponseInterface|ClientExceptionInterface> */
    private array $outcomes = [];

    public function respondWith(int $status, string $body = ''): self
    {
        $this->outcomes[] = new Response($status, [], $body);

        return $this;
    }

    /**
     * @param list<array<string, mixed>> $elements
     */
    public function respondWithElements(array $elements): self
    {
        return $this->respondWith(200, json_encode(['elements' => $elements], JSON_THROW_ON_ERROR));
    }

    public function failWith(string $message): self
    {
        $this->outcomes[] = new class ($message) extends RuntimeException implements ClientExceptionInterface {};

        return $this;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;
        $outcome = array_shift($this->outcomes);

        if ($outcome === null) {
            throw new LogicException('FakeHttpClient received a request it had no outcome queued for');
        }

        if ($outcome instanceof ClientExceptionInterface) {
            throw $outcome;
        }

        return $outcome;
    }
}
