<?php

namespace App\Logging;

use App\Services\TelegramNotificationService;
use Monolog\Handler\AbstractProcessingHandler;
use Throwable;

class TelegramLogHandler extends AbstractProcessingHandler
{
    /**
     * Recursion guard to prevent infinite loops if Telegram logging triggers another log entry.
     */
    public static bool $isHandling = false;

    /**
     * Write the log record to Telegram.
     */
    protected function write(array $record): void
    {
        if (static::$isHandling) {
            return;
        }

        static::$isHandling = true;

        try {
            /** @var TelegramNotificationService $service */
            $service = app(TelegramNotificationService::class);

            if (! $service->isErrorLogEnabled()) {
                return;
            }

            $service->notifyErrorRecord($record);
        } catch (Throwable) {
            // Silently ignore failures in the error logger to prevent crashing the application
        } finally {
            static::$isHandling = false;
        }
    }
}
