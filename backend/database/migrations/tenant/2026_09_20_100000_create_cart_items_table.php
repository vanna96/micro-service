<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('item_id')->index();
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->unsignedBigInteger('uom_id')->nullable();
            $table->json('option_value_ids')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'item_id', 'variant_id', 'uom_id'], 'cart_user_item_variant_uom_unique');
            $table->foreign('item_id')->references('id')->on('items')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};
