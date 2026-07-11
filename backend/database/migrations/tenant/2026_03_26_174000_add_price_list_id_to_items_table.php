<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('items', function (Blueprint $table) {
            $table->foreignId('price_list_id')->nullable()->after('branch_id')->constrained('price_lists')->nullOnDelete();
            $table->index(['price_list_id', 'status']);
        });
    }

    public function down()
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropIndex(['price_list_id', 'status']);
            $table->dropConstrainedForeignId('price_list_id');
        });
    }
};
