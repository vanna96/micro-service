<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('security_settings')) {
            return;
        }

        $now = now();
        foreach ([
            'country_access_enabled' => '0',
            'country_access_mode' => 'allowlist',
            'country_access_codes' => '[]',
        ] as $key => $value) {
            DB::table('security_settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'created_at' => $now, 'updated_at' => $now],
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('security_settings')) {
            DB::table('security_settings')
                ->whereIn('key', [
                    'country_access_enabled',
                    'country_access_mode',
                    'country_access_codes',
                ])
                ->delete();
        }
    }
};
