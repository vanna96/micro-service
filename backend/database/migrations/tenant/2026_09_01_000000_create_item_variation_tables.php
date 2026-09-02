<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_option_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->string('name');
            $table->string('foreign_name')->nullable();
            $table->enum('type', ['variant', 'modifier'])->default('variant');
            $table->enum('selection_type', ['single', 'multiple'])->default('single');
            $table->boolean('is_required')->default(false);
            $table->unsignedSmallInteger('min_selections')->default(0);
            $table->unsignedSmallInteger('max_selections')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->timestamps();

            $table->unique(['item_id', 'name']);
            $table->index(['item_id', 'type', 'status']);
        });

        Schema::create('item_option_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_option_group_id')->constrained('item_option_groups')->cascadeOnDelete();
            $table->string('name');
            $table->string('foreign_name')->nullable();
            $table->string('sku_suffix', 64)->nullable();
            $table->string('color_hex', 16)->nullable();
            $table->decimal('price_adjustment', 12, 2)->default(0);
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->timestamps();

            $table->unique(['item_option_group_id', 'name']);
            $table->index(['item_option_group_id', 'status']);
        });

        Schema::create('item_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->string('sku', 64)->unique();
            $table->string('barcode', 128)->nullable()->unique();
            $table->string('name')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->unsignedInteger('stock')->default(0);
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->timestamps();

            $table->index(['item_id', 'status']);
        });

        Schema::create('item_variant_option_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_variant_id')->constrained('item_variants')->cascadeOnDelete();
            $table->foreignId('item_option_value_id')->constrained('item_option_values')->cascadeOnDelete();

            $table->unique(['item_variant_id', 'item_option_value_id'], 'item_variant_option_value_unique');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('item_variant_id')
                ->nullable()
                ->after('item_id')
                ->constrained('item_variants')
                ->nullOnDelete();
            $table->json('selected_options')->nullable()->after('image_url');
            $table->decimal('option_total', 12, 2)->default(0)->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('item_variant_id');
            $table->dropColumn(['selected_options', 'option_total']);
        });

        Schema::dropIfExists('item_variant_option_values');
        Schema::dropIfExists('item_variants');
        Schema::dropIfExists('item_option_values');
        Schema::dropIfExists('item_option_groups');
    }
};
