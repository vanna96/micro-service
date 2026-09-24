<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('security_logs')) {
            Schema::table('security_logs', function (Blueprint $table) {
                if (! Schema::hasColumn('security_logs', 'country_code')) {
                    $table->string('country_code', 10)->nullable()->after('ip_address')->index();
                }
                if (! Schema::hasColumn('security_logs', 'country_name')) {
                    $table->string('country_name', 100)->nullable()->after('country_code');
                }
                if (! Schema::hasColumn('security_logs', 'city')) {
                    $table->string('city', 100)->nullable()->after('country_name');
                }
                if (! Schema::hasColumn('security_logs', 'is_vpn')) {
                    $table->boolean('is_vpn')->default(false)->after('city')->index();
                }
                if (! Schema::hasColumn('security_logs', 'isp')) {
                    $table->string('isp', 150)->nullable()->after('is_vpn');
                }
            });
        }

        if (Schema::hasTable('security_settings')) {
            $now = now();
            $newSettings = [
                ['key' => 'block_vpn_proxies', 'value' => '0', 'created_at' => $now, 'updated_at' => $now],
                ['key' => 'block_tor_nodes', 'value' => '1', 'created_at' => $now, 'updated_at' => $now],
            ];

            foreach ($newSettings as $setting) {
                if (! DB::table('security_settings')->where('key', $setting['key'])->exists()) {
                    DB::table('security_settings')->insert($setting);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('security_logs')) {
            Schema::table('security_logs', function (Blueprint $table) {
                $columns = ['country_code', 'country_name', 'city', 'is_vpn', 'isp'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('security_logs', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('security_settings')) {
            DB::table('security_settings')->whereIn('key', ['block_vpn_proxies', 'block_tor_nodes'])->delete();
        }
    }
};
