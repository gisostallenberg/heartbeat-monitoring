<?php

declare(strict_types=1);

namespace GisoStallenberg\HeartbeatMonitoring\Tests;

use GisoStallenberg\HeartbeatMonitoring\HeartbeatFactory;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;

final class RunTest extends TestCase
{
    private MockClient $client;
    private HeartbeatFactory $factory;

    protected function setUp(): void
    {
        $psr17 = new Psr17Factory();
        $this->client = new MockClient($psr17);
        $this->factory = new HeartbeatFactory($this->client, $psr17, $psr17);
    }

    public function testRunPingsStartThenSuccessAndReturnsResult(): void
    {
        $result = $this->factory->fromDsn('healthchecks-io://:abc@default')->run(fn (): int => 42);

        self::assertSame(42, $result);
        self::assertCount(2, $this->client->requests);
        self::assertStringContainsString('/abc/start?rid=', (string) $this->client->requests[0]->getUri());
        self::assertStringContainsString('/abc/0?rid=', (string) $this->client->requests[1]->getUri());
    }

    public function testRunPingsFailOnExceptionAndRethrows(): void
    {
        $heartbeat = $this->factory->fromDsn('healthchecks-io://:abc@default');

        try {
            $heartbeat->run(function (): never {
                throw new \RuntimeException('boom');
            });
            self::fail('Expected exception to propagate.');
        } catch (\RuntimeException $e) {
            self::assertSame('boom', $e->getMessage());
        }

        self::assertCount(2, $this->client->requests);
        self::assertStringContainsString('/abc/start?rid=', (string) $this->client->requests[0]->getUri());
        self::assertStringContainsString('/abc/1?rid=', (string) $this->client->requests[1]->getUri());
        self::assertSame('boom', (string) $this->client->requests[1]->getBody());
    }

    public function testStartAndFinishShareOneRunId(): void
    {
        $this->factory->fromDsn('healthchecks-io://:abc@default')->run(fn (): bool => true);

        parse_str((string) $this->client->requests[0]->getUri()->getQuery(), $start);
        parse_str((string) $this->client->requests[1]->getUri()->getQuery(), $finish);
        self::assertSame($start['rid'], $finish['rid']);
    }
}
