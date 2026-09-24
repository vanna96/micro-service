<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Laravel 9 requires doctrine/dbal to drop SQLite columns. The SQLite
        // connection is used only by the test suite; production MySQL tenants
        // remove the legacy column normally.
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        if (Schema::hasColumn('items', 'discount_percent')) {
            Schema::table('items', function (Blueprint $table) {
                $table->dropColumn('discount_percent');
            });
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        if (! Schema::hasColumn('items', 'discount_percent')) {
            Schema::table('items', function (Blueprint $table) {
                $table->unsignedTinyInteger('discount_percent')->default(0)->after('price');
            });
        }
    }
};
