<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->unsignedBigInteger('image_id')->nullable();
            $table->string('sku', 64)->unique();
            $table->string('name');
            $table->string('foreign_name')->nullable();
            $table->string('branch_name')->nullable();
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->unsignedTinyInteger('discount_percent')->default(0);
            $table->decimal('rating', 3, 2)->nullable();
            $table->unsignedInteger('review_count')->default(0);
            $table->unsignedInteger('stock')->default(0);
            $table->boolean('is_premium')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_new_arrival')->default(false);
            $table->boolean('is_try_on_enabled')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->timestamps();

            $table->index(['status', 'category_id']);
            $table->index(['branch_name', 'status']);
            $table->index(['is_featured', 'is_new_arrival']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('items');
    }
};
