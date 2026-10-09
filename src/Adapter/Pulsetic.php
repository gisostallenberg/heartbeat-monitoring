<?php

declare(strict_types=1);

namespace GisoStallenberg\HeartbeatMonitoring\Adapter;

use GisoStallenberg\HeartbeatMonitoring\Ping;
use GisoStallenberg\HeartbeatMonitoring\State;
use Psr\Http\Message\RequestInterface;

/**
 * Pulsetic heartbeat monitors (SaaS). Success-only: a missed ping is detected by
 * Pulsetic itself, so start, fail and log are no-ops.
 *
 *   pulsetic://:<heartbeat-id>@default
 *
 * @see https://help.pulsetic.com/article/478-how-to-set-up-a-heartbeat-monitor
 */
final class Pulsetic extends HttpAdapter
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
            ? 'https://app.pulsetic.com'
            : $this->scheme() . '://' . $this->host();

        return $this->get($base . '/api/v1/heartbeat/' . rawurlencode($this->credential()));
    }
}
