<?php

declare(strict_types=1);

namespace GisoStallenberg\HeartbeatMonitoring;

/**
 * The lifecycle signals a heartbeat can emit. Not every backend supports every
 * one; an adapter that cannot represent a signal returns null (a no-op).
 */
enum State: string
{
    case Start = 'start';
    case Success = 'success';
    case Fail = 'fail';
    case Log = 'log';
}
