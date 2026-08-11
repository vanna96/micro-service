<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('uom_groups', function (Blueprint $table) {
            if (! Schema::hasColumn('uom_groups', 'base_unit_id')) {
                $table->foreignId('base_unit_id')->nullable()->after('description')->constrained('units_of_measure')->nullOnDelete();
            }
        });

        if (! Schema::hasTable('uom_group_units')) {
            Schema::create('uom_group_units', function (Blueprint $table) {
                $table->id();
                $table->foreignId('uom_group_id')->constrained('uom_groups')->cascadeOnDelete();
                $table->foreignId('unit_of_measure_id')->constrained('units_of_measure')->cascadeOnDelete();
                $table->decimal('alternate_quantity', 18, 6)->default(1);
                $table->decimal('base_quantity', 18, 6)->default(1);
                $table->decimal('conversion_factor_to_base', 18, 6)->default(1);
                $table->boolean('is_base_unit')->default(false);
                $table->unsignedInteger('sort_order')->default(0);
                $table->enum('status', ['Active', 'Inactive'])->default('Active');
                $table->timestamps();

                $table->unique(['uom_group_id', 'unit_of_measure_id']);
                $table->index(['uom_group_id', 'status', 'sort_order']);
                $table->index(['is_base_unit', 'status']);
            });
        }

        $this->migrateOldGroupedUnits();
    }

    public function down(): void
    {
        Schema::dropIfExists('uom_group_units');

        Schema::table('uom_groups', function (Blueprint $table) {
            if (Schema::hasColumn('uom_groups', 'base_unit_id')) {
                $table->dropConstrainedForeignId('base_unit_id');
            }
        });
    }

    private function migrateOldGroupedUnits(): void
    {
        if (! Schema::hasColumn('units_of_measure', 'uom_group_id')) {
            return;
        }

        DB::table('units_of_measure')
            ->whereNotNull('uom_group_id')
            ->orderBy('uom_group_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->each(function ($oldUnit) {
                $masterUnit = DB::table('units_of_measure')
                    ->whereNull('uom_group_id')
                    ->where('code', $oldUnit->code)
                    ->first();

                if (! $masterUnit) {
                    $masterUnitId = DB::table('units_of_measure')->insertGetId([
                        'uom_group_id' => null,
                        'code' => $oldUnit->code,
                        'name' => $oldUnit->name,
                        'foreign_name' => $oldUnit->foreign_name,
                        'symbol' => $oldUnit->symbol,
                        'alternate_quantity' => 1,
                        'base_quantity' => 1,
                        'conversion_factor_to_base' => 1,
                        'is_base_unit' => false,
                        'decimal_places' => $oldUnit->decimal_places ?? 2,
                        'sort_order' => 0,
                        'status' => $oldUnit->status,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    $masterUnitId = $masterUnit->id;
                }

                DB::table('uom_group_units')->updateOrInsert(
                    [
                        'uom_group_id' => $oldUnit->uom_group_id,
                        'unit_of_measure_id' => $masterUnitId,
                    ],
                    [
                        'alternate_quantity' => $oldUnit->alternate_quantity ?? 1,
                        'base_quantity' => $oldUnit->base_quantity ?? 1,
                        'conversion_factor_to_base' => $oldUnit->conversion_factor_to_base ?? 1,
                        'is_base_unit' => (bool) $oldUnit->is_base_unit,
                        'sort_order' => $oldUnit->sort_order ?? 0,
                        'status' => $oldUnit->status,
                        'created_at' => $oldUnit->created_at ?? now(),
                        'updated_at' => now(),
                    ]
                );

                if ((bool) $oldUnit->is_base_unit) {
                    DB::table('uom_groups')
                        ->where('id', $oldUnit->uom_group_id)
                        ->update(['base_unit_id' => $masterUnitId]);
                }
            });
    }
};
