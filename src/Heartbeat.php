<?php

declare(strict_types=1);

namespace GisoStallenberg\HeartbeatMonitoring;

/**
 * A configured heartbeat for one monitor. Obtain one from {@see HeartbeatFactory}.
 *
 * Every signal method accepts either a {@see Ping} or a plain string (shorthand
 * for a message). Network and unsupported-signal failures are logged, never
 * thrown — a monitoring outage must not crash the job being monitored.
 */
interface Heartbeat
{
    public function start(Ping|string|null $ping = null): void;

    public function success(Ping|string|null $ping = null): void;

    public function fail(Ping|string|null $ping = null): void;

    /** Record an event without changing status (where the backend supports it). */
    public function log(Ping|string|null $ping = null): void;

    /** Whether this backend can represent the given signal. */
    public function supports(State $state): bool;

    /**
     * Run $job wrapped in start -> success / fail(on exception), timing it and
     * pairing the two pings with a generated run id. Re-throws the job's exception.
     *
     * @template T
     * @param callable():T $job
     * @return T
     */
    public function run(callable $job): mixed;
}
