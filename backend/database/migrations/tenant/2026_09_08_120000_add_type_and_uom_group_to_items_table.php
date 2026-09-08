<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->enum('item_type', ['uom', 'variation'])->default('uom')->after('category_id');
            $table->foreignId('uom_group_id')
                ->nullable()
                ->after('item_type')
                ->constrained('uom_groups')
                ->nullOnDelete();
            $table->index(['item_type', 'status']);
        });

        DB::table('items')
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('item_option_groups')
                    ->whereColumn('item_option_groups.item_id', 'items.id');
            })
            ->update([
                'item_type' => 'variation',
                'uom_group_id' => null,
            ]);
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropIndex(['item_type', 'status']);
            $table->dropConstrainedForeignId('uom_group_id');
            $table->dropColumn('item_type');
        });
    }
};
