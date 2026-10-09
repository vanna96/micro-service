<?php

namespace App\Jobs;

use App\Services\TelegramNotificationService;
use App\Services\TenantJobContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

class SendTelegramMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 20;

    public array $backoff = [5, 30, 60];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $htmlMessage,
        public ?string $botToken = null,
        public ?string $chatId = null,
        public array|string|null $replyMarkup = null,
        public ?string $tenantId = null
    ) {
        $this->tenantId = $tenantId ?: (function_exists('tenant') ? (tenant('id') ?: null) : null);
    }

    /**
     * Execute the job.
     */
    public function handle(TelegramNotificationService $telegram): void
    {
        TenantJobContext::run($this->tenantId, function () use ($telegram): void {
            if (! $telegram->sendDirectMessage($this->htmlMessage, $this->botToken, $this->chatId, $this->replyMarkup)) {
                throw new RuntimeException('Telegram message delivery failed.');
            }
        });
    }
}
