<?php

declare(strict_types=1);

namespace GisoStallenberg\HeartbeatMonitoring\Adapter;

use GisoStallenberg\HeartbeatMonitoring\Ping;
use GisoStallenberg\HeartbeatMonitoring\State;
use Psr\Http\Message\RequestInterface;

/**
 * healthchecks.io (and self-hosted instances).
 *
 *   healthchecks-io://:<uuid>@default                     (UUID mode)
 *   healthchecks-io://:<ping-key>@default/?name=<slug>    (ping-key + slug mode)
 *   healthchecks-io://:<uuid>@hc.example.com/ping         (self-hosted, path = ping prefix)
 *
 * @see https://healthchecks.io/docs/http_api/
 */
final class HealthchecksIo extends HttpAdapter
{
    public function supports(State $state): bool
    {
        return true;
    }

    protected function buildRequest(State $state, Ping $ping): ?RequestInterface
    {
        $url = $this->endpoint() . match ($state) {
            State::Start => '/start',
            State::Fail => '/' . ($ping->exitCode ?? 'fail'),
            State::Success => $ping->exitCode !== null ? '/' . $ping->exitCode : '',
            State::Log => '/log',
        };

        if ($ping->runId !== null) {
            $url .= '?rid=' . rawurlencode($ping->runId);
        }

        return $ping->message !== null ? $this->post($url, $ping->message) : $this->get($url);
    }

    private function endpoint(): string
    {
        $base = $this->dsn->isDefaultHost()
            ? 'https://hc-ping.com'
            : $this->scheme() . '://' . $this->host() . rtrim($this->dsn->path, '/');

        $base .= '/' . rawurlencode($this->credential());

        // With a ?name=, the credential is a project ping key and name is the check slug.
        if (($slug = $this->dsn->name()) !== null) {
            $base .= '/' . rawurlencode($slug);
        }

        return $base;
    }
}
