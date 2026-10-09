<?php

declare(strict_types=1);

namespace GisoStallenberg\HeartbeatMonitoring\Tests;

use GisoStallenberg\HeartbeatMonitoring\Dsn;
use GisoStallenberg\HeartbeatMonitoring\Exception\InvalidDsn;
use GisoStallenberg\HeartbeatMonitoring\Exception\UnsupportedScheme;
use GisoStallenberg\HeartbeatMonitoring\HeartbeatFactory;
use PHPUnit\Framework\TestCase;

final class DsnTest extends TestCase
{
    public function testParsesCredentialFromPassword(): void
    {
        $dsn = Dsn::parse('healthchecks-io://:tok%20en@default/?name=my-check');

        self::assertSame('healthchecks-io', $dsn->scheme);
        self::assertSame('tok en', $dsn->credential); // url-decoded
        self::assertTrue($dsn->isDefaultHost());
        self::assertSame('my-check', $dsn->name());
    }

    public function testParsesCredentialFromUserWhenNoPassword(): void
    {
        self::assertSame('pub', Dsn::parse('sentry://pub@o1.ingest.sentry.io/42')->credential);
    }

    public function testMalformedDsnThrows(): void
    {
        $this->expectException(InvalidDsn::class);
        Dsn::parse('not a dsn');
    }

    public function testUnknownSchemeThrows(): void
    {
        $this->expectException(UnsupportedScheme::class);
        HeartbeatFactory::create('nope://:x@default');
    }
}
