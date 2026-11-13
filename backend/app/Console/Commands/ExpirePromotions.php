<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Repositories\PromotionRepository;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

class ExpirePromotions extends Command
{
    protected $signature = 'promotions:expire
                            {--tenant=* : Only process these tenant IDs}
                            {--at= : Expire promotions at this date and time instead of now}';

    protected $description = 'Mark ended active promotions as inactive for every tenant';

    public function handle(PromotionRepository $promotions): int
    {
        try {
            $at = filled($this->option('at'))
                ? CarbonImmutable::parse((string) $this->option('at'))
                : CarbonImmutable::now();
        } catch (Throwable $exception) {
            $this->error('Invalid --at value: '.$exception->getMessage());

            return self::INVALID;
        }

        $tenantIds = array_values(array_filter(array_map('strval', (array) $this->option('tenant'))));
        $query = Tenant::query()->orderBy('id');
        if ($tenantIds !== []) {
            $query->whereIn('id', $tenantIds);
        }

        $tenants = $query->get();
        if ($tenants->isEmpty()) {
            $this->warn('No matching tenants were found.');

            return self::SUCCESS;
        }

        $expired = 0;
        $failed = 0;

        foreach ($tenants as $tenant) {
            try {
                tenancy()->initialize($tenant);
                $count = $promotions->expireEndedPromotions($at);
                $expired += $count;
                $this->line(sprintf('<info>%s</info>: %d expired', $tenant->id, $count));
            } catch (Throwable $exception) {
                $failed++;
                $this->error($tenant->id.': '.$exception->getMessage());
            } finally {
                if (tenancy()->initialized) {
                    tenancy()->end();
                }
            }
        }

        $this->info(sprintf(
            'Promotion expiration finished: %d promotion(s) updated across %d tenant(s), %d tenant(s) failed.',
            $expired,
            $tenants->count(),
            $failed
        ));

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
