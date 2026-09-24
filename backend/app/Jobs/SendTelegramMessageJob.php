<?php

namespace App\Jobs;

use App\Services\TelegramNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

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
        try {
            $tenant = $this->tenantId ? \App\Models\Tenant::find($this->tenantId) : null;
            if ($tenant && (! function_exists('tenant') || ! tenant() || (string) tenant('id') !== (string) $this->tenantId)) {
                $tenant->run(function () use ($telegram) {
                    $telegram->sendDirectMessage($this->htmlMessage, $this->botToken, $this->chatId, $this->replyMarkup);
                });
            } else {
                $telegram->sendDirectMessage($this->htmlMessage, $this->botToken, $this->chatId, $this->replyMarkup);
            }
        } catch (Throwable $e) {
            Log::error('[SendTelegramMessageJob] Failed to deliver telegram notification: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
        }
    }
}
