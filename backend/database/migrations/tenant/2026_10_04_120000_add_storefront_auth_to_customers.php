<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('username')->nullable()->unique()->after('name');
            $table->string('password')->nullable()->after('email');
            $table->string('first_name')->nullable()->after('password');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('country_code', 10)->nullable()->after('last_name');
            $table->enum('gender', ['Male', 'Female'])->nullable()->after('phone');
            $table->date('dob')->nullable()->after('gender');
            $table->rememberToken();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn([
                'username',
                'password',
                'first_name',
                'last_name',
                'country_code',
                'gender',
                'dob',
                'remember_token',
            ]);
        });
    }
};
