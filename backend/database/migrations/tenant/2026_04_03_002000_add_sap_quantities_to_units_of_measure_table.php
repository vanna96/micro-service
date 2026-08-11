<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units_of_measure', function (Blueprint $table) {
            if (! Schema::hasColumn('units_of_measure', 'alternate_quantity')) {
                $table->decimal('alternate_quantity', 18, 6)->default(1)->after('symbol');
            }

            if (! Schema::hasColumn('units_of_measure', 'base_quantity')) {
                $table->decimal('base_quantity', 18, 6)->default(1)->after('alternate_quantity');
            }
        });

        DB::table('units_of_measure')
            ->whereNull('alternate_quantity')
            ->orWhere('alternate_quantity', 0)
            ->update(['alternate_quantity' => 1]);

        DB::table('units_of_measure')
            ->where('base_quantity', 1)
            ->where('conversion_factor_to_base', '<>', 1)
            ->update(['base_quantity' => DB::raw('conversion_factor_to_base')]);

        DB::table('units_of_measure')
            ->where('is_base_unit', true)
            ->update([
                'alternate_quantity' => 1,
                'base_quantity' => 1,
                'conversion_factor_to_base' => 1,
            ]);
    }

    public function down(): void
    {
        Schema::table('units_of_measure', function (Blueprint $table) {
            if (Schema::hasColumn('units_of_measure', 'base_quantity')) {
                $table->dropColumn('base_quantity');
            }

            if (Schema::hasColumn('units_of_measure', 'alternate_quantity')) {
                $table->dropColumn('alternate_quantity');
            }
        });
    }
};
