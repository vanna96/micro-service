<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $central = DB::connection('central');

        foreach ($central->table('tenants')->select('id', 'alias', 'db_name')->get() as $tenant) {
            $legacyAlias = Str::of((string) $tenant->id)->beforeLast('_db')->toString();
            $currentAlias = (string) ($tenant->alias ?? '');

            if ($legacyAlias === (string) $tenant->id || $currentAlias !== Str::slug((string) $tenant->id)) {
                continue;
            }

            $alias = Str::slug($legacyAlias);
            if ($alias === '' || $central->table('tenants')->where('alias', $alias)->where('id', '!=', $tenant->id)->exists()) {
                continue;
            }

            $central->table('tenants')->where('id', $tenant->id)->update(['alias' => $alias]);
        }
    }

    public function down(): void
    {
        // Alias values are user-facing identifiers; they are not safely reversible.
    }
};
