<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rate_index_values', function (Blueprint $table) {
            $table->id();
            $table->enum('dataset_type', ['exchange_rate', 'index']);
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->unsignedTinyInteger('day');
            $table->foreignId('currency_id')->constrained('currencies')->cascadeOnDelete();
            $table->decimal('value', 18, 8);
            $table->timestamps();

            $table->unique(
                ['dataset_type', 'year', 'month', 'day', 'currency_id'],
                'rate_index_values_unique_cell'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rate_index_values');
    }
};
