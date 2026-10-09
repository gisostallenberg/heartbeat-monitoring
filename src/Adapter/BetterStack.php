<?php

declare(strict_types=1);

namespace GisoStallenberg\HeartbeatMonitoring\Adapter;

use GisoStallenberg\HeartbeatMonitoring\Ping;
use GisoStallenberg\HeartbeatMonitoring\State;
use Psr\Http\Message\RequestInterface;

/**
 * Better Stack (formerly Better Uptime) heartbeats.
 *
 *   better-stack://:<token>@default
 *   better-stack://:<token>@incidents.betterstack.com   (if your URL uses that host)
 *
 * @see https://betterstack.com/docs/uptime/cron-and-heartbeat-monitor/
 */
final class BetterStack extends HttpAdapter
{
    public function supports(State $state): bool
    {
        return $state === State::Success || $state === State::Fail;
    }

    protected function buildRequest(State $state, Ping $ping): ?RequestInterface
    {
        $base = ($this->dsn->isDefaultHost()
            ? 'https://uptime.betterstack.com'
            : $this->scheme() . '://' . $this->host())
            . '/api/v1/heartbeat/' . rawurlencode($this->credential());

        $url = match ($state) {
            State::Success => $ping->exitCode !== null ? $base . '/' . $ping->exitCode : $base,
            State::Fail => $base . '/' . ($ping->exitCode ?? 'fail'),
            default => null,
        };

        if ($url === null) {
            return null;
        }

        return $ping->message !== null ? $this->post($url, $ping->message) : $this->get($url);
    }
}
