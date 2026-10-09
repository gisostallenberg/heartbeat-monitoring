<?php

declare(strict_types=1);

namespace GisoStallenberg\HeartbeatMonitoring\Adapter;

use GisoStallenberg\HeartbeatMonitoring\Ping;
use GisoStallenberg\HeartbeatMonitoring\State;
use Psr\Http\Message\RequestInterface;

/**
 * Cronitor telemetry pings.
 *
 *   cronitor://:<api-key>@default/?name=<monitor-key>    (US)
 *   cronitor://:<api-key>@eu/?name=<monitor-key>         (EU ingest)
 *
 * @see https://cronitor.io/docs/telemetry-api
 */
final class Cronitor extends HttpAdapter
{
    public function supports(State $state): bool
    {
        return true;
    }

    protected function buildRequest(State $state, Ping $ping): ?RequestInterface
    {
        $query = [
            'state' => match ($state) {
                State::Start => 'run',
                State::Success => 'complete',
                State::Fail => 'fail',
                State::Log => 'tick',
            },
        ];

        if ($ping->message !== null) {
            $query['message'] = $ping->message;
        }
        if ($ping->exitCode !== null) {
            $query['status_code'] = (string) $ping->exitCode;
        }
        if ($ping->duration !== null) {
            $query['metric'] = 'duration:' . round($ping->duration, 3);
        }
        if ($ping->runId !== null) {
            $query['series'] = $ping->runId;
        }

        $url = $this->base()
            . '/p/' . rawurlencode($this->credential())
            . '/' . rawurlencode($this->requireName())
            . '?' . http_build_query($query);

        return $this->get($url);
    }

    private function base(): string
    {
        return 'https://' . match ($this->dsn->host) {
            'default' => 'cronitor.link',
            'eu' => 'eu.cronitor.link',
            default => $this->host(),
        };
    }
}
