<?php

declare(strict_types=1);

namespace GisoStallenberg\HeartbeatMonitoring\Adapter;

use GisoStallenberg\HeartbeatMonitoring\Dsn;
use GisoStallenberg\HeartbeatMonitoring\Exception\InvalidDsn;
use GisoStallenberg\HeartbeatMonitoring\Heartbeat;
use GisoStallenberg\HeartbeatMonitoring\Ping;
use GisoStallenberg\HeartbeatMonitoring\State;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Shared machinery for the HTTP-ping based adapters: it normalises arguments,
 * sends requests, times {@see run()}, and swallows+logs transport errors so a
 * failing monitor never takes the monitored job down with it.
 *
 * Subclasses implement {@see buildRequest()} (map a signal to a request, or null
 * to skip it) and {@see supports()}.
 */
abstract class HttpAdapter implements Heartbeat
{
    private ClientInterface $client;
    private RequestFactoryInterface $requestFactory;
    private StreamFactoryInterface $streamFactory;

    public function __construct(
        protected readonly Dsn $dsn,
        ?ClientInterface $client = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        protected readonly LoggerInterface $logger = new NullLogger(),
    ) {
        $this->client = $client ?? Psr18ClientDiscovery::find();
        $this->requestFactory = $requestFactory ?? Psr17FactoryDiscovery::findRequestFactory();
        $this->streamFactory = $streamFactory ?? Psr17FactoryDiscovery::findStreamFactory();
    }

    /** Map a signal to a request to send, or null when this backend has no such signal. */
    abstract protected function buildRequest(State $state, Ping $ping): ?RequestInterface;

    public function start(Ping|string|null $ping = null): void
    {
        $this->send(State::Start, self::ping($ping));
    }

    public function success(Ping|string|null $ping = null): void
    {
        $this->send(State::Success, self::ping($ping));
    }

    public function fail(Ping|string|null $ping = null): void
    {
        $this->send(State::Fail, self::ping($ping));
    }

    public function log(Ping|string|null $ping = null): void
    {
        $this->send(State::Log, self::ping($ping));
    }

    public function run(callable $job): mixed
    {
        $runId = self::uuid();
        $this->start(new Ping(runId: $runId));
        $startedAt = microtime(true);

        try {
            $result = $job();
        } catch (\Throwable $e) {
            $this->fail(new Ping(
                message: $e->getMessage(),
                exitCode: 1,
                duration: microtime(true) - $startedAt,
                runId: $runId,
            ));

            throw $e;
        }

        $this->success(new Ping(
            exitCode: 0,
            duration: microtime(true) - $startedAt,
            runId: $runId,
        ));

        return $result;
    }

    private function send(State $state, Ping $ping): void
    {
        $request = $this->buildRequest($state, $ping);
        if ($request === null) {
            $this->logger->warning('heartbeat: "{scheme}" has no "{state}" signal; skipped', [
                'scheme' => $this->dsn->scheme,
                'state' => $state->value,
            ]);

            return;
        }

        try {
            $this->client->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            $this->logger->error('heartbeat: failed to send "{state}" to "{scheme}": {error}', [
                'scheme' => $this->dsn->scheme,
                'state' => $state->value,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private static function ping(Ping|string|null $ping): Ping
    {
        return $ping instanceof Ping ? $ping : new Ping(message: $ping);
    }

    protected function get(string $url): RequestInterface
    {
        return $this->withHeaders($this->requestFactory->createRequest('GET', $url));
    }

    protected function post(string $url, string $body): RequestInterface
    {
        return $this->withHeaders(
            $this->requestFactory
                ->createRequest('POST', $url)
                ->withBody($this->streamFactory->createStream($body)),
        );
    }

    /**
     * Extra request headers; override to add auth (e.g. Opsgenie's GenieKey).
     *
     * @return array<string, string>
     */
    protected function headers(): array
    {
        return [];
    }

    private function withHeaders(RequestInterface $request): RequestInterface
    {
        foreach ($this->headers() as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return $request;
    }

    /** Transport scheme; override with ?scheme=http for a plain-HTTP self-hosted instance. */
    protected function scheme(): string
    {
        return ($this->dsn->query['scheme'] ?? 'https') === 'http' ? 'http' : 'https';
    }

    protected function host(): string
    {
        return $this->dsn->host . ($this->dsn->port !== null ? ':' . $this->dsn->port : '');
    }

    protected function credential(): string
    {
        return $this->dsn->credential
            ?? throw new InvalidDsn(sprintf('DSN "%s" is missing a token/credential.', $this->dsn->scheme));
    }

    protected function requireName(): string
    {
        return $this->dsn->name()
            ?? throw new InvalidDsn(sprintf('DSN "%s" requires a ?name= parameter.', $this->dsn->scheme));
    }

    /** A random UUIDv4, accepted by healthchecks rid / cronitor series / Sentry check_in_id. */
    private static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
