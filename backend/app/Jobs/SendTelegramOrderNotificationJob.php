<?php

namespace App\Jobs;

use App\Models\PosSale;
use App\Models\Tenant;
use App\Services\TelegramNotificationService;
use App\Services\TenantJobContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use InvalidArgumentException;
use RuntimeException;

class SendTelegramOrderNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 30;

    public array $backoff = [5, 30, 60];

    /**
     * Create a new job instance.
     *
     * @param  string  $type  'pos_sale'
     * @param  int|string  $modelId  ID of the PosSale
     * @param  string|null  $tenantId  Optional tenant ID
     */
    public function __construct(
        public string $type,
        public int|string $modelId,
        public ?string $tenantId = null
    ) {
        $this->tenantId = $tenantId ?: (tenant('id') ?: null);
    }

    /**
     * Execute the job.
     */
    public function handle(TelegramNotificationService $telegram): void
    {
        if ($this->type !== 'pos_sale') {
            throw new InvalidArgumentException('Unsupported Telegram notification type.');
        }

        TenantJobContext::run($this->tenantId, function (?Tenant $tenant) use ($telegram): void {
            if (! $telegram->isEnabled($tenant)) {
                return;
            }

            $sale = PosSale::query()->with('items')->find($this->modelId);
            if ($sale && ! $telegram->notifyPosSale($sale, $tenant)) {
                throw new RuntimeException('Telegram order notification delivery failed.');
            }
        });
    }
}
