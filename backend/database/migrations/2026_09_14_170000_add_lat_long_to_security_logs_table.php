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
                if (! Schema::hasColumn('security_logs', 'latitude')) {
                    $table->decimal('latitude', 10, 7)->nullable()->after('city');
                }
                if (! Schema::hasColumn('security_logs', 'longitude')) {
                    $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
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
                $cols = [];
                if (Schema::hasColumn('security_logs', 'latitude')) {
                    $cols[] = 'latitude';
                }
                if (Schema::hasColumn('security_logs', 'longitude')) {
                    $cols[] = 'longitude';
                }
                if (! empty($cols)) {
                    $table->dropColumn($cols);
                }
            });
        }
    }
};
