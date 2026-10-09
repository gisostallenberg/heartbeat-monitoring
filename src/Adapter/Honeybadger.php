<?php

declare(strict_types=1);

namespace GisoStallenberg\HeartbeatMonitoring\Adapter;

use GisoStallenberg\HeartbeatMonitoring\Ping;
use GisoStallenberg\HeartbeatMonitoring\State;
use Psr\Http\Message\RequestInterface;

/**
 * Honeybadger check-ins. Success-only; a missed check-in alerts.
 *
 *   honeybadger://:<check-in-id>@default
 *   honeybadger://:<project-api-key>@default/?name=<slug>
 *
 * @see https://docs.honeybadger.io/api/reporting-check-ins/
 */
final class Honeybadger extends HttpAdapter
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

        $base = $this->dsn->isDefaultHost() ? 'https://api.honeybadger.io' : $this->scheme() . '://' . $this->host();
        $url = $base . '/v1/check_in/' . rawurlencode($this->credential());

        // With a ?name=, the credential is the project API key and name is the check-in slug.
        if (($slug = $this->dsn->name()) !== null) {
            $url .= '/' . rawurlencode($slug);
        }

        return $this->get($url);
    }
}
