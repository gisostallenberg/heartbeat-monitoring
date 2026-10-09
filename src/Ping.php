<?php

declare(strict_types=1);

namespace GisoStallenberg\HeartbeatMonitoring;

/**
 * Optional metadata attached to a signal. Which fields a backend uses depends on
 * the adapter (see the capability table in the README); unused fields are ignored.
 */
final readonly class Ping
{
    public function __construct(
        public ?string $message = null,
        /** Process exit code: 0 = success, 1-255 = failure (healthchecks, cronitor status_code). */
        public ?int $exitCode = null,
        /** Run duration in seconds (cronitor metric, Uptime Kuma ping=). */
        public ?float $duration = null,
        /** Correlates a start with its finish (healthchecks rid, cronitor series, Sentry check_in_id). */
        public ?string $runId = null,
    ) {
    }
}
