<?php

declare(strict_types=1);

namespace GisoStallenberg\HeartbeatMonitoring\Adapter;

use GisoStallenberg\HeartbeatMonitoring\Ping;
use GisoStallenberg\HeartbeatMonitoring\State;
use Psr\Http\Message\RequestInterface;

/**
 * Opsgenie (Atlassian) heartbeat ping. Success-only ("alive"); a missed ping alerts.
 * Authenticates with an API-integration key via the GenieKey header.
 *
 *   opsgenie://:<api-key>@default/?name=<heartbeat-name>   (US)
 *   opsgenie://:<api-key>@eu/?name=<heartbeat-name>        (EU)
 *
 * @see https://docs.opsgenie.com/docs/heartbeat-api
 */
final class Opsgenie extends HttpAdapter
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

        $host = match ($this->dsn->host) {
            'default' => 'api.opsgenie.com',
            'eu' => 'api.eu.opsgenie.com',
            default => $this->host(),
        };

        return $this->get('https://' . $host . '/v2/heartbeats/' . rawurlencode($this->requireName()) . '/ping');
    }

    /** @return array<string, string> */
    protected function headers(): array
    {
        return ['Authorization' => 'GenieKey ' . $this->credential()];
    }
}
