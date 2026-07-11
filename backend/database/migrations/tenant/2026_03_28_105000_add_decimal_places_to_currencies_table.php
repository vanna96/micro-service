<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('currencies')) {
            return;
        }

        if (! Schema::hasColumn('currencies', 'decimal_places')) {
            Schema::table('currencies', function (Blueprint $table) {
                $table->unsignedTinyInteger('decimal_places')->default(2)->after('symbol');
            });
        }

        DB::table('currencies')
            ->whereNull('decimal_places')
            ->update(['decimal_places' => 2]);

        DB::table('currencies')
            ->where('code', 'KHR')
            ->update(['decimal_places' => 0]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('currencies') || ! Schema::hasColumn('currencies', 'decimal_places')) {
            return;
        }

        Schema::table('currencies', function (Blueprint $table) {
            $table->dropColumn('decimal_places');
        });
    }
};
