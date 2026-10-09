<?php

declare(strict_types=1);

namespace GisoStallenberg\HeartbeatMonitoring;

use GisoStallenberg\HeartbeatMonitoring\Exception\InvalidDsn;

/**
 * Parsed heartbeat DSN: scheme://[user]:[token]@host[/path]?name=...&...
 *
 * The credential is the password if present, otherwise the user (so both
 * `:token@` and `token@` work, and Sentry's `publicKey@` fits). Host `default`
 * means "use the adapter's built-in SaaS host".
 */
final readonly class Dsn
{
    /** @param array<string, string> $query */
    public function __construct(
        public string $scheme,
        public ?string $credential,
        public string $host,
        public ?int $port,
        public string $path,
        public array $query,
    ) {
    }

    public static function parse(string $dsn): self
    {
        $parts = parse_url($dsn);
        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            throw new InvalidDsn(sprintf('Malformed heartbeat DSN: "%s".', $dsn));
        }

        $credential = $parts['pass'] ?? $parts['user'] ?? null;
        if ($credential !== null) {
            $credential = urldecode($credential);
            if ($credential === '') {
                $credential = null;
            }
        }

        parse_str($parts['query'] ?? '', $query);

        /** @var array<string, string> $query */
        return new self(
            strtolower($parts['scheme']),
            $credential,
            $parts['host'],
            $parts['port'] ?? null,
            $parts['path'] ?? '',
            $query,
        );
    }

    public function isDefaultHost(): bool
    {
        return $this->host === 'default';
    }

    public function name(): ?string
    {
        return $this->query['name'] ?? null;
    }
}
