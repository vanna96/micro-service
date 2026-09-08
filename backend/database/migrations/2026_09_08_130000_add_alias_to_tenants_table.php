<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->table('tenants', function (Blueprint $table): void {
            $table->string('alias')->nullable()->after('id');
        });

        $central = DB::connection('central');

        foreach ($central->table('tenants')->select('id')->get() as $tenant) {
            $domain = $central->table('domains')
                ->where('tenant_id', $tenant->id)
                ->orderBy('id')
                ->value('domain');

            $legacyAlias = Str::of((string) $tenant->id)->beforeLast('_db')->toString();
            $alias = $legacyAlias !== (string) $tenant->id
                ? $legacyAlias
                : ($domain ? Str::before($domain, '.') : (string) $tenant->id);
            $alias = Str::slug($alias ?: (string) $tenant->id);
            $baseAlias = $alias;
            $suffix = 2;

            while ($central->table('tenants')->where('alias', $alias)->exists()) {
                $alias = $baseAlias . '-' . $suffix++;
            }

            $central->table('tenants')
                ->where('id', $tenant->id)
                ->update(['alias' => $alias]);
        }

        Schema::connection('central')->table('tenants', function (Blueprint $table): void {
            $table->unique('alias');
        });
    }

    public function down(): void
    {
        Schema::connection('central')->table('tenants', function (Blueprint $table): void {
            $table->dropUnique(['alias']);
            $table->dropColumn('alias');
        });
    }
};
