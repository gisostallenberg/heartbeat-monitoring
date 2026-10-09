<?php

declare(strict_types=1);

namespace GisoStallenberg\HeartbeatMonitoring\Tests;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;

/** Captures every request and returns a canned 200, so nothing hits the network. */
final class MockClient implements ClientInterface
{
    /** @var list<RequestInterface> */
    public array $requests = [];

    public function __construct(private readonly ResponseFactoryInterface $responses)
    {
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;

        return $this->responses->createResponse(200);
    }

    public function last(): RequestInterface
    {
        return $this->requests[array_key_last($this->requests)];
    }
}
