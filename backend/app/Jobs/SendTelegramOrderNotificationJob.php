<?php

namespace App\Jobs;

use App\Models\PosSale;
use App\Models\Tenant;
use App\Services\TelegramNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

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

    /**
     * Create a new job instance.
     *
     * @param string $type 'pos_sale' or 'order'
     * @param int|string $modelId ID of the PosSale or Order
     * @param string|null $tenantId Optional tenant ID
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
        try {
            $tenant = null;
            if ($this->tenantId && (! tenancy()->initialized || tenant('id') !== $this->tenantId)) {
                $tenant = Tenant::find($this->tenantId);
                if ($tenant) {
                    tenancy()->initialize($tenant);
                }
            } else {
                $tenant = tenant();
            }

            $sale = PosSale::query()->with('items')->find($this->modelId);
            if ($sale) {
                $telegram->notifyPosSale($sale, $tenant);
            }
        } catch (Throwable $e) {
            Log::error("[SendTelegramOrderNotificationJob] Failed to send notification for {$this->type} #{$this->modelId}: " . $e->getMessage(), [
                'exception' => $e,
            ]);
        }
    }
}
