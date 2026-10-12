<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Throwable;

class OptimizeCatalogIndexes extends Command
{
    protected $signature = 'catalog:optimize-indexes
                            {--tenant=* : Only optimize these tenant IDs}
                            {--status : Report missing indexes without creating them}';

    protected $description = 'Idempotently add the indexes used by mobile and POS catalog reads';

    /** @var array<string, array<string, array<int, string>>> */
    private const INDEXES = [
        'items' => [
            'items_catalog_latest_idx' => ['status', 'sale', 'id'],
            'items_catalog_category_idx' => ['status', 'sale', 'category_id', 'id'],
            'items_catalog_branch_idx' => ['status', 'sale', 'branch_id', 'id'],
            'items_catalog_featured_idx' => ['status', 'sale', 'is_featured', 'sort_order', 'id'],
            'items_catalog_arrival_idx' => ['status', 'sale', 'is_new_arrival', 'id'],
            'items_catalog_seller_idx' => ['status', 'sale', 'review_count', 'rating', 'id'],
        ],
        'categories' => [
            'categories_catalog_idx' => ['status', 'id'],
        ],
        'currencies' => [
            'currencies_catalog_idx' => ['status', 'sort_order', 'code'],
        ],
        'sliders' => [
            'sliders_catalog_idx' => ['status', 'placement', 'sort_order', 'id'],
        ],
        'promotions' => [
            'promotions_active_idx' => ['status', 'start_at', 'end_at'],
        ],
    ];

    public function handle(): int
    {
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

        $failed = 0;
        foreach ($tenants as $tenant) {
            try {
                tenancy()->initialize($tenant);
                [$created, $missing] = $this->optimizeCurrentTenant((bool) $this->option('status'));
                $message = $this->option('status')
                    ? sprintf('%d missing', $missing)
                    : sprintf('%d created, %d missing', $created, $missing);
                $this->line(sprintf('<info>%s</info>: %s', $tenant->id, $message));
            } catch (Throwable $exception) {
                $failed++;
                $this->error($tenant->id.': '.$exception->getMessage());
            } finally {
                if (tenancy()->initialized) {
                    tenancy()->end();
                }
            }
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /** @return array{0: int, 1: int} */
    private function optimizeCurrentTenant(bool $statusOnly): array
    {
        $schema = Schema::connection(tenant()->database_connection_name ?: 'tenant');
        $created = 0;
        $missing = 0;

        foreach (self::INDEXES as $table => $definitions) {
            if (! $schema->hasTable($table)) {
                continue;
            }

            $existing = collect($schema->getIndexes($table))
                ->pluck('name')
                ->filter()
                ->map(fn ($name) => strtolower((string) $name))
                ->all();

            foreach ($definitions as $name => $columns) {
                if (in_array(strtolower($name), $existing, true)) {
                    continue;
                }

                $missing++;
                if ($statusOnly) {
                    continue;
                }

                $schema->table($table, function (Blueprint $blueprint) use ($columns, $name): void {
                    $blueprint->index($columns, $name);
                });
                $created++;
                $missing--;
            }
        }

        return [$created, $missing];
    }
}
