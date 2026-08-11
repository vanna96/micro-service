<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('uom_groups', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->string('foreign_name')->nullable();
            $table->text('description')->nullable();
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->timestamps();

            $table->index(['status', 'name']);
        });

        Schema::create('units_of_measure', function (Blueprint $table) {
            $table->id();
            $table->foreignId('uom_group_id')->nullable()->constrained('uom_groups')->cascadeOnDelete();
            $table->string('code', 64);
            $table->string('name');
            $table->string('foreign_name')->nullable();
            $table->string('symbol', 32)->nullable();
            $table->decimal('alternate_quantity', 18, 6)->default(1);
            $table->decimal('base_quantity', 18, 6)->default(1);
            $table->decimal('conversion_factor_to_base', 18, 6)->default(1);
            $table->boolean('is_base_unit')->default(false);
            $table->unsignedTinyInteger('decimal_places')->default(2);
            $table->unsignedInteger('sort_order')->default(0);
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->timestamps();

            $table->unique(['uom_group_id', 'code']);
            $table->index('code');
            $table->index(['uom_group_id', 'status', 'sort_order']);
            $table->index(['is_base_unit', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units_of_measure');
        Schema::dropIfExists('uom_groups');
    }
};
