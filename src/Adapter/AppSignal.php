<?php

declare(strict_types=1);

namespace GisoStallenberg\HeartbeatMonitoring\Adapter;

use GisoStallenberg\HeartbeatMonitoring\Ping;
use GisoStallenberg\HeartbeatMonitoring\State;
use Psr\Http\Message\RequestInterface;

/**
 * AppSignal cron check-ins. start/finish only; a finish that never arrives alerts.
 *
 *   appsignal://:<app-api-key>@default/?name=<identifier>
 *
 * @see https://docs.appsignal.com/check-ins/configuration/api.html
 */
final class AppSignal extends HttpAdapter
{
    public function supports(State $state): bool
    {
        return $state === State::Start || $state === State::Success;
    }

    protected function buildRequest(State $state, Ping $ping): ?RequestInterface
    {
        $kind = match ($state) {
            State::Start => 'start',
            State::Success => 'finish',
            default => null,
        };

        if ($kind === null) {
            return null;
        }

        $query = [
            'api_key' => $this->credential(),
            'identifier' => $this->requireName(),
            'kind' => $kind,
        ];
        if ($ping->runId !== null) {
            $query['digest'] = $ping->runId; // pairs start with finish, enabling duration
        }

        $base = $this->dsn->isDefaultHost() ? 'https://appsignal-endpoint.net' : $this->scheme() . '://' . $this->host();

        return $this->post($base . '/check_ins/cron?' . http_build_query($query), '');
    }
}
