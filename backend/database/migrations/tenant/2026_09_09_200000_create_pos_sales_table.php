<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_sales', function (Blueprint $table) {
            $table->id();
            $table->uuid('client_token');
            $table->uuid('sale_key');
            $table->string('status', 16)->default('current');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->json('snapshot');
            $table->timestamps();

            $table->unique(['client_token', 'sale_key']);
            $table->index(['client_token', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_sales');
    }
};
