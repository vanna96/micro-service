<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->enum('type', ['item_price', 'subtotal_discount', 'bogo']);
            $table->text('description')->nullable();
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->decimal('threshold_amount', 12, 2)->nullable();
            $table->unsignedTinyInteger('reward_discount_percent')->nullable();
            $table->unsignedInteger('buy_quantity')->nullable();
            $table->unsignedInteger('get_quantity')->nullable();
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index(['start_at', 'end_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('promotions');
    }
};
