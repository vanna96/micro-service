<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_uom_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignId('unit_of_measure_id')->constrained('units_of_measure')->cascadeOnDelete();
            $table->decimal('reduce_by_percent', 5, 2)->default(0);
            $table->decimal('price', 20, 8)->nullable();
            $table->boolean('is_auto')->default(true);
            $table->timestamps();

            $table->unique(['item_id', 'unit_of_measure_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_uom_prices');
    }
};
