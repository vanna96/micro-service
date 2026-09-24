<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_sales', function (Blueprint $table) {
            if (! Schema::hasColumn('pos_sales', 'stock_deducted_at')) {
                $table->timestamp('stock_deducted_at')->nullable()->index()->after('completed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pos_sales', function (Blueprint $table) {
            if (Schema::hasColumn('pos_sales', 'stock_deducted_at')) {
                $table->dropColumn('stock_deducted_at');
            }
        });
    }
};
