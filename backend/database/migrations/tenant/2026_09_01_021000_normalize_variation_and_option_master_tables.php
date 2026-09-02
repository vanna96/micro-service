<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('item_variations') || ! Schema::hasTable('item_option_masters')) {
            return;
        }

        Schema::table('item_option_master_values', function (Blueprint $table) {
            $table->dropForeign(['item_option_master_id']);
        });

        Schema::table('item_option_groups', function (Blueprint $table) {
            $table->dropForeign(['item_option_master_id']);
        });

        Schema::rename('item_option_masters', 'item_variations');
        Schema::rename('item_option_master_values', 'item_options');

        Schema::table('item_options', function (Blueprint $table) {
            $table->renameColumn('item_option_master_id', 'item_variation_id');
            $table->foreign('item_variation_id')->references('id')->on('item_variations')->cascadeOnDelete();
        });

        Schema::table('item_option_groups', function (Blueprint $table) {
            $table->renameColumn('item_option_master_id', 'item_variation_id');
            $table->foreign('item_variation_id')->references('id')->on('item_variations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('item_option_masters') || ! Schema::hasTable('item_variations')) {
            return;
        }

        Schema::table('item_options', function (Blueprint $table) {
            $table->dropForeign(['item_variation_id']);
        });

        Schema::table('item_option_groups', function (Blueprint $table) {
            $table->dropForeign(['item_variation_id']);
        });

        Schema::table('item_options', function (Blueprint $table) {
            $table->renameColumn('item_variation_id', 'item_option_master_id');
        });

        Schema::table('item_option_groups', function (Blueprint $table) {
            $table->renameColumn('item_variation_id', 'item_option_master_id');
        });

        Schema::rename('item_options', 'item_option_master_values');
        Schema::rename('item_variations', 'item_option_masters');

        Schema::table('item_option_master_values', function (Blueprint $table) {
            $table->foreign('item_option_master_id')->references('id')->on('item_option_masters')->cascadeOnDelete();
        });

        Schema::table('item_option_groups', function (Blueprint $table) {
            $table->foreign('item_option_master_id')->references('id')->on('item_option_masters')->nullOnDelete();
        });
    }
};
