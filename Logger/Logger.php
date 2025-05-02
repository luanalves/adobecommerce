<?php
declare(strict_types=1);

namespace TheDevKitchen\JwtCrossDomainAuth\Logger;

use Monolog\Logger as MonologLogger;

class Logger extends MonologLogger
{
    public const AUTH_CHANNEL = 'jwt_auth';

    public function __construct($handlers = [], $processors = [])
    {
        parent::__construct(self::AUTH_CHANNEL, $handlers, $processors);
    }
}