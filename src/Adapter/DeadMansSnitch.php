<?php

declare(strict_types=1);

namespace GisoStallenberg\HeartbeatMonitoring\Adapter;

use GisoStallenberg\HeartbeatMonitoring\Ping;
use GisoStallenberg\HeartbeatMonitoring\State;
use Psr\Http\Message\RequestInterface;

/**
 * Dead Man's Snitch (SaaS). The classic dead-man's-switch: a missed check-in alerts.
 *
 *   dead-mans-snitch://:<token>@default
 *
 * @see https://deadmanssnitch.com/docs
 */
final class DeadMansSnitch extends HttpAdapter
{
    public function supports(State $state): bool
    {
        return $state === State::Success || $state === State::Fail;
    }

    protected function buildRequest(State $state, Ping $ping): ?RequestInterface
    {
        $status = match ($state) {
            State::Success => '0',
            State::Fail => (string) ($ping->exitCode ?? 1),
            default => null,
        };

        if ($status === null) {
            return null;
        }

        $query = ['s' => $status];
        if ($ping->message !== null) {
            $query['m'] = substr($ping->message, 0, 256);
        }

        $base = $this->dsn->isDefaultHost() ? 'https://nosnch.in' : $this->scheme() . '://' . $this->host();

        return $this->get($base . '/' . rawurlencode($this->credential()) . '?' . http_build_query($query));
    }
}
