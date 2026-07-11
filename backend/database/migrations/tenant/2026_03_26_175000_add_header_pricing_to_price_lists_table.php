<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('price_lists', function (Blueprint $table) {
            $table->enum('header_pricing_method', ['fixed', 'discount'])->nullable()->after('description');
            $table->decimal('header_fixed_price', 12, 2)->nullable()->after('header_pricing_method');
            $table->unsignedTinyInteger('header_discount_percent')->nullable()->after('header_fixed_price');
        });
    }

    public function down()
    {
        Schema::table('price_lists', function (Blueprint $table) {
            $table->dropColumn([
                'header_pricing_method',
                'header_fixed_price',
                'header_discount_percent',
            ]);
        });
    }
};
