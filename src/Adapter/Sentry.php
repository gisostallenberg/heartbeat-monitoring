<?php

declare(strict_types=1);

namespace GisoStallenberg\HeartbeatMonitoring\Adapter;

use GisoStallenberg\HeartbeatMonitoring\Exception\InvalidDsn;
use GisoStallenberg\HeartbeatMonitoring\Ping;
use GisoStallenberg\HeartbeatMonitoring\State;
use Psr\Http\Message\RequestInterface;

/**
 * Sentry Crons check-ins. Built straight from a Sentry DSN (public key @ ingest
 * host / project id); the monitor slug is the ?name=.
 *
 *   sentry://<public-key>@o123456.ingest.sentry.io/<project-id>?name=<monitor-slug>
 *   sentry://<public-key>@sentry.example.com/<project-id>?name=<slug>   (self-hosted)
 *
 * @see https://docs.sentry.io/product/crons/getting-started/http/
 */
final class Sentry extends HttpAdapter
{
    public function supports(State $state): bool
    {
        return $state !== State::Log;
    }

    protected function buildRequest(State $state, Ping $ping): ?RequestInterface
    {
        $status = match ($state) {
            State::Start => 'in_progress',
            State::Success => 'ok',
            State::Fail => 'error',
            State::Log => null,
        };

        if ($status === null) {
            return null;
        }

        if ($this->dsn->isDefaultHost()) {
            throw new InvalidDsn('sentry requires the ingest host from your Sentry DSN, not "default".');
        }

        $projectId = trim($this->dsn->path, '/');
        if ($projectId === '') {
            throw new InvalidDsn('sentry DSN must include the project id in the path, e.g. .../<project-id>?name=slug');
        }

        $query = ['status' => $status];
        if ($ping->runId !== null) {
            $query['check_in_id'] = $ping->runId;
        }

        $url = sprintf(
            '%s://%s/api/%s/cron/%s/%s/?%s',
            $this->scheme(),
            $this->host(),
            $projectId,
            rawurlencode($this->requireName()),
            rawurlencode($this->credential()),
            http_build_query($query),
        );

        return $this->get($url);
    }
}
