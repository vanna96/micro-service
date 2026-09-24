<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
                if (! Schema::hasColumn('security_logs', 'tenant_id')) {
                    $table->string('tenant_id', 100)->nullable()->after('incident_id')->index();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('security_logs')) {
            Schema::table('security_logs', function (Blueprint $table) {
                if (Schema::hasColumn('security_logs', 'tenant_id')) {
                    $table->dropColumn('tenant_id');
                }
            });
        }
    }
};
