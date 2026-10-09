<?php

declare(strict_types=1);

namespace GisoStallenberg\HeartbeatMonitoring\Exception;

final class UnsupportedScheme extends \InvalidArgumentException
{
    public function __construct(string $scheme)
    {
        parent::__construct(sprintf('No heartbeat adapter registered for DSN scheme "%s".', $scheme));
    }
}
