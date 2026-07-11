<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sliders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('image_id')->nullable();
            $table->string('title')->nullable();
            $table->string('subtitle')->nullable();
            $table->string('placement', 32);
            $table->string('target_url')->nullable();
            $table->string('recommended_dimensions')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status', 32)->default('Active');
            $table->timestamps();

            $table->index(['placement', 'status']);
            $table->index(['status', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sliders');
    }
};
