<?php

declare(strict_types=1);

namespace GisoStallenberg\HeartbeatMonitoring;

use GisoStallenberg\HeartbeatMonitoring\Adapter\AppSignal;
use GisoStallenberg\HeartbeatMonitoring\Adapter\BetterStack;
use GisoStallenberg\HeartbeatMonitoring\Adapter\Cronitor;
use GisoStallenberg\HeartbeatMonitoring\Adapter\DeadMansSnitch;
use GisoStallenberg\HeartbeatMonitoring\Adapter\HealthchecksIo;
use GisoStallenberg\HeartbeatMonitoring\Adapter\Honeybadger;
use GisoStallenberg\HeartbeatMonitoring\Adapter\Opsgenie;
use GisoStallenberg\HeartbeatMonitoring\Adapter\Pulsetic;
use GisoStallenberg\HeartbeatMonitoring\Adapter\Sentry;
use GisoStallenberg\HeartbeatMonitoring\Adapter\UptimeKuma;
use GisoStallenberg\HeartbeatMonitoring\Adapter\UptimeRobot;
use GisoStallenberg\HeartbeatMonitoring\Exception\UnsupportedScheme;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Builds a {@see Heartbeat} from a DSN. The HTTP client and PSR-17 factories are
 * optional: when omitted they are auto-discovered from whatever is installed.
 */
final class HeartbeatFactory
{
    /** @var array<string, class-string<Heartbeat>> */
    private const ADAPTERS = [
        'healthchecks-io' => HealthchecksIo::class,
        'uptime-kuma' => UptimeKuma::class,
        'pulsetic' => Pulsetic::class,
        'cronitor' => Cronitor::class,
        'sentry' => Sentry::class,
        'better-stack' => BetterStack::class,
        'dead-mans-snitch' => DeadMansSnitch::class,
        'honeybadger' => Honeybadger::class,
        'opsgenie' => Opsgenie::class,
        'appsignal' => AppSignal::class,
        'uptime-robot' => UptimeRobot::class,
    ];

    public function __construct(
        private readonly ?ClientInterface $client = null,
        private readonly ?RequestFactoryInterface $requestFactory = null,
        private readonly ?StreamFactoryInterface $streamFactory = null,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function fromDsn(string $dsn): Heartbeat
    {
        $parsed = Dsn::parse($dsn);
        $adapter = self::ADAPTERS[$parsed->scheme] ?? throw new UnsupportedScheme($parsed->scheme);

        return new $adapter($parsed, $this->client, $this->requestFactory, $this->streamFactory, $this->logger);
    }

    /** @return list<string> The supported DSN schemes. */
    public static function schemes(): array
    {
        return array_keys(self::ADAPTERS);
    }

    /** One-liner with auto-discovered HTTP client and factories. */
    public static function create(string $dsn): Heartbeat
    {
        return (new self())->fromDsn($dsn);
    }
}
