<?php

declare(strict_types=1);

namespace GisoStallenberg\HeartbeatMonitoring\Adapter;

use GisoStallenberg\HeartbeatMonitoring\Ping;
use GisoStallenberg\HeartbeatMonitoring\State;
use Psr\Http\Message\RequestInterface;

/**
 * UptimeRobot heartbeat monitors. Success-only ("alive"); a missed ping marks it down.
 * The token is the opaque segment from the monitor's generated URL (e.g. m12345-abcdef).
 *
 *   uptime-robot://:<token>@default
 *
 * @see https://uptimerobot.com/blog/new-feature-heartbeat-monitoring/
 */
final class UptimeRobot extends HttpAdapter
{
    public function supports(State $state): bool
    {
        return $state === State::Success;
    }

    protected function buildRequest(State $state, Ping $ping): ?RequestInterface
    {
        if ($state !== State::Success) {
            return null;
        }

        $base = $this->dsn->isDefaultHost()
            ? 'https://heartbeat.uptimerobot.com'
            : $this->scheme() . '://' . $this->host();

        return $this->get($base . '/' . rawurlencode($this->credential()));
    }
}
