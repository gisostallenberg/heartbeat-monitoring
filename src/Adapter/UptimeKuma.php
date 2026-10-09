<?php

declare(strict_types=1);

namespace GisoStallenberg\HeartbeatMonitoring\Adapter;

use GisoStallenberg\HeartbeatMonitoring\Exception\InvalidDsn;
use GisoStallenberg\HeartbeatMonitoring\Ping;
use GisoStallenberg\HeartbeatMonitoring\State;
use Psr\Http\Message\RequestInterface;

/**
 * Uptime Kuma push monitors (always self-hosted; no "default" host).
 *
 *   uptime-kuma://:<push-token>@kuma.example.com
 *   uptime-kuma://:<push-token>@kuma.local:3001/?scheme=http
 *
 * Only up/down exists, so start and log are no-ops.
 *
 * @see https://github.com/louislam/uptime-kuma/wiki/Internal-API
 */
final class UptimeKuma extends HttpAdapter
{
    public function supports(State $state): bool
    {
        return $state === State::Success || $state === State::Fail;
    }

    protected function buildRequest(State $state, Ping $ping): ?RequestInterface
    {
        $status = match ($state) {
            State::Success => 'up',
            State::Fail => 'down',
            default => null,
        };

        if ($status === null) {
            return null;
        }

        if ($this->dsn->isDefaultHost()) {
            throw new InvalidDsn('uptime-kuma requires your instance host, e.g. uptime-kuma://:token@kuma.example.com');
        }

        $query = ['status' => $status];
        if ($ping->message !== null) {
            $query['msg'] = $ping->message;
        }
        if ($ping->duration !== null) {
            $query['ping'] = (string) round($ping->duration * 1000);
        }

        $url = $this->scheme() . '://' . $this->host()
            . '/api/push/' . rawurlencode($this->credential())
            . '?' . http_build_query($query);

        return $this->get($url);
    }
}
