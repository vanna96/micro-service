<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('units_of_measure') || ! Schema::hasColumn('units_of_measure', 'uom_group_id')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        try {
            DB::statement('ALTER TABLE units_of_measure DROP FOREIGN KEY units_of_measure_uom_group_id_foreign');
        } catch (Throwable $exception) {
            // Foreign key may already be absent in a freshly adjusted database.
        }

        DB::statement('ALTER TABLE units_of_measure MODIFY uom_group_id BIGINT UNSIGNED NULL');

        try {
            DB::statement('ALTER TABLE units_of_measure ADD CONSTRAINT units_of_measure_uom_group_id_foreign FOREIGN KEY (uom_group_id) REFERENCES uom_groups(id) ON DELETE CASCADE');
        } catch (Throwable $exception) {
            // Keep migration idempotent for local dev resets.
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('units_of_measure') || ! Schema::hasColumn('units_of_measure', 'uom_group_id')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::table('units_of_measure')->whereNull('uom_group_id')->delete();

        try {
            DB::statement('ALTER TABLE units_of_measure DROP FOREIGN KEY units_of_measure_uom_group_id_foreign');
        } catch (Throwable $exception) {
            // Ignore missing FK.
        }

        DB::statement('ALTER TABLE units_of_measure MODIFY uom_group_id BIGINT UNSIGNED NOT NULL');

        try {
            DB::statement('ALTER TABLE units_of_measure ADD CONSTRAINT units_of_measure_uom_group_id_foreign FOREIGN KEY (uom_group_id) REFERENCES uom_groups(id) ON DELETE CASCADE');
        } catch (Throwable $exception) {
            // Ignore FK restore errors in local dev.
        }
    }
};
