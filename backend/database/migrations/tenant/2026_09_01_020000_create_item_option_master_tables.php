<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_variations', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('foreign_name')->nullable();
            $table->enum('type', ['variant', 'modifier'])->default('variant');
            $table->enum('selection_type', ['single', 'multiple'])->default('single');
            $table->boolean('is_required')->default(false);
            $table->unsignedSmallInteger('min_selections')->default(0);
            $table->unsignedSmallInteger('max_selections')->nullable();
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->timestamps();

            $table->index(['type', 'status']);
        });

        Schema::create('item_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_variation_id')->constrained('item_variations')->cascadeOnDelete();
            $table->string('name');
            $table->string('foreign_name')->nullable();
            $table->string('sku_suffix', 64)->nullable();
            $table->string('color_hex', 16)->nullable();
            $table->decimal('price_adjustment', 12, 2)->default(0);
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->timestamps();

            $table->unique(['item_variation_id', 'name'], 'item_option_variation_name_unique');
            $table->index(['item_variation_id', 'status'], 'item_option_variation_status_index');
        });

        Schema::table('item_option_groups', function (Blueprint $table) {
            $table->foreignId('item_variation_id')
                ->nullable()
                ->after('item_id')
                ->constrained('item_variations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('item_option_groups', function (Blueprint $table) {
            if (Schema::hasColumn('item_option_groups', 'item_variation_id')) {
                $table->dropConstrainedForeignId('item_variation_id');
            } elseif (Schema::hasColumn('item_option_groups', 'item_option_master_id')) {
                $table->dropConstrainedForeignId('item_option_master_id');
            }
        });

        Schema::dropIfExists('item_options');
        Schema::dropIfExists('item_variations');
        Schema::dropIfExists('item_option_master_values');
        Schema::dropIfExists('item_option_masters');
    }
};
