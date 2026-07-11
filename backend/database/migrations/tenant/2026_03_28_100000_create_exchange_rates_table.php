<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->string('rate_type', 10)->default('M');
            $table->string('from_currency', 3);
            $table->string('to_currency', 3);
            $table->date('valid_from');
            $table->decimal('exchange_rate', 18, 8);
            $table->unsignedInteger('from_factor')->default(1);
            $table->unsignedInteger('to_factor')->default(1);
            $table->enum('quotation_method', ['Direct', 'Indirect'])->default('Direct');
            $table->string('reference')->nullable();
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->timestamps();

            $table->unique(['rate_type', 'from_currency', 'to_currency', 'valid_from'], 'exchange_rates_unique_rate_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
