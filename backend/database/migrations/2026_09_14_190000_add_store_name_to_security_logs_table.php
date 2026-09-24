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
        Schema::table('security_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('security_logs', 'store_name')) {
                $table->string('store_name')->nullable()->after('tenant_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('security_logs', function (Blueprint $table) {
            if (Schema::hasColumn('security_logs', 'store_name')) {
                $table->dropColumn('store_name');
            }
        });
    }
};
