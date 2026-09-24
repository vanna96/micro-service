<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pos_sales')) {
            Schema::table('pos_sales', function (Blueprint $table) {
                if (! Schema::hasColumn('pos_sales', 'user_id')) {
                    $table->foreignId('user_id')->nullable()->after('customer_id')->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('pos_sales', 'sale_from')) {
                    $table->string('sale_from', 32)->default('web')->index()->after('user_id');
                }
            });
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (! Schema::hasColumn('orders', 'sale_from')) {
                    $table->string('sale_from', 32)->default('mobile')->index()->after('user_id');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pos_sales')) {
            Schema::table('pos_sales', function (Blueprint $table) {
                if (Schema::hasColumn('pos_sales', 'user_id')) {
                    $table->dropConstrainedForeignId('user_id');
                }
                if (Schema::hasColumn('pos_sales', 'sale_from')) {
                    $table->dropIndex(['sale_from']);
                    $table->dropColumn('sale_from');
                }
            });
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (Schema::hasColumn('orders', 'sale_from')) {
                    $table->dropIndex(['sale_from']);
                    $table->dropColumn('sale_from');
                }
            });
        }
    }
};
