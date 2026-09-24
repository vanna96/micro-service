<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            if (! Schema::hasColumn('items', 'purchase')) {
                $table->boolean('purchase')->default(true)->after('stock_control');
            }
            if (! Schema::hasColumn('items', 'sale')) {
                $table->boolean('sale')->default(true)->after('purchase');
            }

            $table->index(['purchase', 'sale']);
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropIndex(['purchase', 'sale']);

            if (Schema::hasColumn('items', 'sale')) {
                $table->dropColumn('sale');
            }
            if (Schema::hasColumn('items', 'purchase')) {
                $table->dropColumn('purchase');
            }
        });
    }
};
