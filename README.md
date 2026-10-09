# heartbeat-monitoring

Adapter-based, **DSN-driven** heartbeat / cron-job / dead-man's-switch monitoring.
One API, swap the backend by changing the DSN scheme.

Supports **healthchecks.io**, **Uptime Kuma**, **Pulsetic**, **Cronitor**, **Sentry**,
**Better Stack**, **Dead Man's Snitch**, **Honeybadger**, **Opsgenie**, **AppSignal**
and **UptimeRobot** out of the box.

Every one is a dead-man's-switch at heart: you set an expected period or schedule in the
service, and a *missed* `success` ping alerts. `start`/`fail` are optional speed-ups on the
backends that have them — never the only way a failure is caught.

## Usage

The only thing that changes between backends is the DSN string — the code stays the same.

### The short way: `run()`

Wrap the job. `run()` pings `start`, times it, then pings `success` — or `fail`
with the exception message if the job throws (and re-throws it). Both pings share a
generated run id, so the backend can pair them and report the duration.

```php
use GisoStallenberg\HeartbeatMonitoring\HeartbeatFactory;

$heartbeat = HeartbeatFactory::create('healthchecks-io://:your-uuid@default');

$rows = $heartbeat->run(function () {
    return importNightlyOrders(); // whatever your cron job does
});
```

A complete cron script:

```php
#!/usr/bin/env php
<?php
require __DIR__ . '/vendor/autoload.php';

use GisoStallenberg\HeartbeatMonitoring\HeartbeatFactory;

$heartbeat = HeartbeatFactory::create($_ENV['HEARTBEAT_DSN']); // swap backend via env, no code change

$heartbeat->run(function () {
    // ... the actual work; throw to report a failure ...
});
```

### The explicit way

When you want to control the lifecycle yourself:

```php
$heartbeat = HeartbeatFactory::create('healthchecks-io://:your-uuid@default');

$heartbeat->start();
try {
    doTheWork();
    $heartbeat->success();
} catch (\Throwable $e) {
    $heartbeat->fail($e->getMessage()); // a plain string is shorthand for a message
    throw $e;
}
```

### With metadata

```php
use GisoStallenberg\HeartbeatMonitoring\Ping;

$heartbeat->success(new Ping(message: 'imported 1 240 orders', duration: 12.4));
$heartbeat->fail(new Ping(message: 'DB timeout', exitCode: 75));
```

### Passive check / dead man's switch

Call `success()` on a schedule from the thing that must stay alive; the backend
alerts when a ping is missed:

```php
HeartbeatFactory::create('pulsetic://:your-heartbeat-id@default')->success();
```

## Install

```bash
composer require gisostallenberg/heartbeat-monitoring
```

Bring any [PSR-18](https://www.php-fig.org/psr/psr-18/) HTTP client and
[PSR-17](https://www.php-fig.org/psr/psr-17/) factories; they are auto-discovered
(e.g. `composer require symfony/http-client nyholm/psr7`). Or inject your own:

```php
$factory = new HeartbeatFactory($psr18Client, $psr17Factory, $psr17Factory, $psrLogger);
$heartbeat = $factory->fromDsn($dsn);
```

## DSN format

A single DSN string fully configures a monitor. It is a standard URL, so it fits in
one environment variable:

```
scheme://[user]:[token]@host[/path]?name=<monitor>&scheme=http
```

Worked example — healthchecks.io with a project ping key and a check slug:

```
healthchecks-io :// : abcd1234ping-key @ default /? name=nightly-import
└──── scheme ───┘   └──── token ─────┘  └─host─┘   └──── name ─────┘
   picks adapter      your credential   SaaS host   which monitor
```

- **scheme** picks the adapter: `healthchecks-io`, `uptime-kuma`, `pulsetic`, `cronitor`, `sentry`.
- **credential** is the password (`:token@`) if present, else the user — so `:token@`,
  `token@` and Sentry's `publicKey@` all work. URL-encode a token that contains `@ : / ?`
  (e.g. a space becomes `%20`).
- **host** `default` = the backend's built-in SaaS host; any other host targets a
  self-hosted instance (healthchecks.io, Uptime Kuma and Sentry can be self-hosted).
- **`/path`** — only used by self-hosted backends that live under a path prefix
  (e.g. healthchecks' `/ping`); ignored for `default`.
- **`?name=`** selects the monitor (slug / monitor-key / Sentry slug) where the backend
  needs it; omitted where the token already identifies the monitor (Uptime Kuma, Pulsetic).
- **`?scheme=http`** forces plain HTTP for a self-hosted instance (default is HTTPS).

Get a `Heartbeat` from a DSN with `HeartbeatFactory::create($dsn)` (auto-discovers an
HTTP client) or `(new HeartbeatFactory($client, ...))->fromDsn($dsn)` (inject your own).
An unknown scheme throws `UnsupportedScheme`; a malformed DSN throws `InvalidDsn`.

| Service | DSN | `name` is |
|---|---|---|
| healthchecks.io | `healthchecks-io://:<uuid>@default` | — (UUID mode) |
| healthchecks.io (slug) | `healthchecks-io://:<ping-key>@default/?name=<slug>` | check slug |
| healthchecks.io (self-hosted) | `healthchecks-io://:<uuid>@hc.example.com/ping` | — |
| Uptime Kuma | `uptime-kuma://:<push-token>@kuma.example.com` | — |
| Pulsetic | `pulsetic://:<heartbeat-id>@default` | — |
| Cronitor | `cronitor://:<api-key>@default/?name=<monitor-key>` (`@eu` for EU) | monitor key |
| Sentry | `sentry://<public-key>@o123.ingest.sentry.io/<project-id>?name=<slug>` | monitor slug |
| Better Stack | `better-stack://:<token>@default` | — |
| Dead Man's Snitch | `dead-mans-snitch://:<token>@default` | — |
| Honeybadger | `honeybadger://:<check-in-id>@default` (or `:<project-key>@default/?name=<slug>`) | check-in slug |
| Opsgenie | `opsgenie://:<api-key>@default/?name=<heartbeat-name>` (`@eu` for EU) | heartbeat name |
| AppSignal | `appsignal://:<app-api-key>@default/?name=<identifier>` | check-in identifier |
| UptimeRobot | `uptime-robot://:<token>@default` | — |

## Signals

The four signals are `start()`, `success()`, `fail()` and `log()`. Not every backend has
every one — `✓` = supported, `–` = **no-op** (skipped and logged as a warning; failure is
still caught the dead-man way). Check first with `supports(State::Fail)`.

| Service | `start` | `success` | `fail` | `log` |
|---|:-:|:-:|:-:|:-:|
| healthchecks.io | ✓ | ✓ | ✓ | ✓ |
| Uptime Kuma | – | ✓ | ✓ | – |
| Pulsetic | – | ✓ | – | – |
| Cronitor | ✓ | ✓ | ✓ | ✓ |
| Sentry | ✓ | ✓ | ✓ | – |
| Better Stack | – | ✓ | ✓ | – |
| Dead Man's Snitch | – | ✓ | ✓ | – |
| Honeybadger | – | ✓ | – | – |
| Opsgenie | – | ✓ | – | – |
| AppSignal | ✓ | ✓ | – | – |
| UptimeRobot | – | ✓ | – | – |

Attach metadata with a `Ping` (used where the backend supports it):

```php
use GisoStallenberg\HeartbeatMonitoring\Ping;

$heartbeat->fail(new Ping(
    message: 'disk full',   // diagnostics / msg
    exitCode: 1,            // healthchecks /{code}, cronitor status_code
    duration: 12.4,         // seconds — cronitor metric, Kuma ping=
    runId: $id,             // pairs start+finish (healthchecks rid, cronitor series, Sentry check_in_id)
));
```

Transport failures are logged, never thrown: a monitoring outage must not crash the
monitored job. `run()` still re-throws the *job's* own exception after pinging `fail`.

## Notes

- **Uptime Kuma** uses its "internal" push API — stable in practice but not contractually versioned.
- **Pulsetic** is success-only by design (missed pings are detected server-side).
- **Cronitor** auto-creates a monitor on first ping; EU ingest via `@eu`.
- **Sentry** reuses your project's DSN — the ingest host, project id and public key come straight from it.
- **Better Stack** — if your heartbeat URL uses the `incidents.betterstack.com` host, set it
  explicitly: `better-stack://:<token>@incidents.betterstack.com`.
- **Opsgenie** authenticates with the `Authorization: GenieKey <api-key>` header; EU via `@eu`.
- **AppSignal** needs the **app-level** push/API key (not the org key), from the app's
  "Push & deploy" settings.
- **UptimeRobot** — the `<token>` is the opaque segment of the monitor's generated URL (e.g.
  `m123-abcdef` from `https://heartbeat.uptimerobot.com/m123-abcdef`). UptimeRobot doesn't
  publish this URL's exact format, so verify yours against the monitor settings.

## License

MIT
