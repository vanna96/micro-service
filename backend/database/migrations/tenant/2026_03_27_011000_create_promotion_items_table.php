<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('promotion_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained('promotions')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->enum('line_role', ['item', 'buy', 'get'])->default('item');
            $table->enum('pricing_method', ['fixed', 'discount'])->nullable();
            $table->decimal('fixed_price', 12, 2)->nullable();
            $table->unsignedTinyInteger('discount_percent')->nullable();
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->timestamps();

            $table->unique(['promotion_id', 'item_id', 'line_role']);
            $table->index(['item_id', 'status']);
            $table->index(['promotion_id', 'status']);
            $table->index(['promotion_id', 'line_role', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('promotion_items');
    }
};
