<?php

namespace App\Logging;

use Monolog\Logger;

class TelegramLogger
{
    /**
     * Create a custom Monolog instance for Telegram error logging.
     */
    public function __invoke(array $config): Logger
    {
        $level = $config['level'] ?? config('telegram.error_log_level', 'error');
        $monologLevel = Logger::toMonologLevel($level);

        $handler = new TelegramLogHandler($monologLevel);

        return new Logger('telegram', [$handler]);
    }
}
