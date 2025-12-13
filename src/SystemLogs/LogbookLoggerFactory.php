<?php

namespace EmranAlhaddad\StatamicLogbook\SystemLogs;

use Monolog\Logger;
use Monolog\Level;

class LogbookLoggerFactory
{
    public function __invoke(array $config): Logger
    {
        $levelName = $config['level'] ?? 'debug';
        $level = Level::fromName($levelName);

        $handler = new DbSystemLogHandler(
            level: $level,
            bubble: $config['bubble'] ?? true,
            channel: $config['name'] ?? null
        );

        $logger = new Logger($config['name'] ?? 'logbook');
        $logger->pushHandler($handler);

        return $logger;
    }
}
