<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasColumn('customers', 'type')) {
                $table->string('type', 30)->default('customer')->after('notes');
                $table->index(['type', 'status']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (Schema::hasColumn('customers', 'type')) {
                $table->dropIndex(['type', 'status']);
                $table->dropColumn('type');
            }
        });
    }
};
