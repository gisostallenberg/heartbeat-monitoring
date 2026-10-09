<?php

declare(strict_types=1);

namespace GisoStallenberg\HeartbeatMonitoring\Tests;

use GisoStallenberg\HeartbeatMonitoring\HeartbeatFactory;
use GisoStallenberg\HeartbeatMonitoring\Ping;
use GisoStallenberg\HeartbeatMonitoring\State;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AdapterTest extends TestCase
{
    private MockClient $client;
    private HeartbeatFactory $factory;

    protected function setUp(): void
    {
        $psr17 = new Psr17Factory();
        $this->client = new MockClient($psr17);
        $this->factory = new HeartbeatFactory($this->client, $psr17, $psr17);
    }

    /** @param 'start'|'success'|'fail'|'log' $signal */
    #[DataProvider('mappings')]
    public function testSignalBuildsExpectedRequest(string $dsn, string $signal, string $method, string $url): void
    {
        $this->factory->fromDsn($dsn)->{$signal}();

        self::assertSame($method, $this->client->last()->getMethod());
        self::assertSame($url, (string) $this->client->last()->getUri());
    }

    /** @return iterable<string, array{string, string, string, string}> */
    public static function mappings(): iterable
    {
        yield 'healthchecks start' => ['healthchecks-io://:abc@default', 'start', 'GET', 'https://hc-ping.com/abc/start'];
        yield 'healthchecks success' => ['healthchecks-io://:abc@default', 'success', 'GET', 'https://hc-ping.com/abc'];
        yield 'healthchecks fail' => ['healthchecks-io://:abc@default', 'fail', 'GET', 'https://hc-ping.com/abc/fail'];
        yield 'healthchecks log' => ['healthchecks-io://:abc@default', 'log', 'GET', 'https://hc-ping.com/abc/log'];
        yield 'healthchecks slug mode' => ['healthchecks-io://:pk@default/?name=backup', 'success', 'GET', 'https://hc-ping.com/pk/backup'];
        yield 'healthchecks self-hosted' => ['healthchecks-io://:abc@hc.example.com/ping', 'start', 'GET', 'https://hc.example.com/ping/abc/start'];

        yield 'kuma success' => ['uptime-kuma://:tok@kuma.example.com', 'success', 'GET', 'https://kuma.example.com/api/push/tok?status=up'];
        yield 'kuma fail' => ['uptime-kuma://:tok@kuma.example.com', 'fail', 'GET', 'https://kuma.example.com/api/push/tok?status=down'];
        yield 'kuma http override' => ['uptime-kuma://:tok@kuma.local:3001/?scheme=http', 'success', 'GET', 'http://kuma.local:3001/api/push/tok?status=up'];

        yield 'pulsetic success' => ['pulsetic://:id123@default', 'success', 'GET', 'https://app.pulsetic.com/api/v1/heartbeat/id123'];

        yield 'cronitor start' => ['cronitor://:key@default/?name=job', 'start', 'GET', 'https://cronitor.link/p/key/job?state=run'];
        yield 'cronitor success' => ['cronitor://:key@default/?name=job', 'success', 'GET', 'https://cronitor.link/p/key/job?state=complete'];
        yield 'cronitor fail' => ['cronitor://:key@default/?name=job', 'fail', 'GET', 'https://cronitor.link/p/key/job?state=fail'];
        yield 'cronitor eu' => ['cronitor://:key@eu/?name=job', 'success', 'GET', 'https://eu.cronitor.link/p/key/job?state=complete'];

        yield 'sentry start' => ['sentry://pub@o1.ingest.sentry.io/42?name=cron', 'start', 'GET', 'https://o1.ingest.sentry.io/api/42/cron/cron/pub/?status=in_progress'];
        yield 'sentry success' => ['sentry://pub@o1.ingest.sentry.io/42?name=cron', 'success', 'GET', 'https://o1.ingest.sentry.io/api/42/cron/cron/pub/?status=ok'];
        yield 'sentry fail' => ['sentry://pub@o1.ingest.sentry.io/42?name=cron', 'fail', 'GET', 'https://o1.ingest.sentry.io/api/42/cron/cron/pub/?status=error'];

        yield 'better-stack success' => ['better-stack://:tok@default', 'success', 'GET', 'https://uptime.betterstack.com/api/v1/heartbeat/tok'];
        yield 'better-stack fail' => ['better-stack://:tok@default', 'fail', 'GET', 'https://uptime.betterstack.com/api/v1/heartbeat/tok/fail'];

        yield 'snitch success' => ['dead-mans-snitch://:tok@default', 'success', 'GET', 'https://nosnch.in/tok?s=0'];
        yield 'snitch fail' => ['dead-mans-snitch://:tok@default', 'fail', 'GET', 'https://nosnch.in/tok?s=1'];

        yield 'honeybadger success' => ['honeybadger://:cid@default', 'success', 'GET', 'https://api.honeybadger.io/v1/check_in/cid'];
        yield 'honeybadger slug' => ['honeybadger://:pk@default/?name=nightly', 'success', 'GET', 'https://api.honeybadger.io/v1/check_in/pk/nightly'];

        yield 'opsgenie success' => ['opsgenie://:key@default/?name=db-backup', 'success', 'GET', 'https://api.opsgenie.com/v2/heartbeats/db-backup/ping'];
        yield 'opsgenie eu' => ['opsgenie://:key@eu/?name=db-backup', 'success', 'GET', 'https://api.eu.opsgenie.com/v2/heartbeats/db-backup/ping'];

        yield 'appsignal start' => ['appsignal://:key@default/?name=importer', 'start', 'POST', 'https://appsignal-endpoint.net/check_ins/cron?api_key=key&identifier=importer&kind=start'];
        yield 'appsignal success' => ['appsignal://:key@default/?name=importer', 'success', 'POST', 'https://appsignal-endpoint.net/check_ins/cron?api_key=key&identifier=importer&kind=finish'];

        yield 'uptime-robot success' => ['uptime-robot://:m123-abc@default', 'success', 'GET', 'https://heartbeat.uptimerobot.com/m123-abc'];
    }

    public function testOpsgenieSendsGenieKeyHeader(): void
    {
        $this->factory->fromDsn('opsgenie://:secret-key@default/?name=db-backup')->success();

        self::assertSame('GenieKey secret-key', $this->client->last()->getHeaderLine('Authorization'));
    }

    public function testUnsupportedSignalIsNoOp(): void
    {
        // Pulsetic is success-only; start/fail must not hit the client.
        $this->factory->fromDsn('pulsetic://:id123@default')->fail();

        self::assertSame([], $this->client->requests);
    }

    public function testMessageSwitchesHealthchecksToPost(): void
    {
        $this->factory->fromDsn('healthchecks-io://:abc@default')->fail('it broke');

        self::assertSame('POST', $this->client->last()->getMethod());
        self::assertSame('it broke', (string) $this->client->last()->getBody());
    }

    public function testExitCodeMapsToHealthchecksSuffix(): void
    {
        $this->factory->fromDsn('healthchecks-io://:abc@default')->success(new Ping(exitCode: 0));

        self::assertSame('https://hc-ping.com/abc/0', (string) $this->client->last()->getUri());
    }

    public function testSupports(): void
    {
        self::assertFalse($this->factory->fromDsn('pulsetic://:id@default')->supports(State::Fail));
        self::assertTrue($this->factory->fromDsn('cronitor://:k@default/?name=j')->supports(State::Start));
    }
}
