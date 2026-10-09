<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->timestamp('email_verified_at')->nullable()->after('email');
            $table->string('email_verification_code_hash')->nullable()->after('email_verified_at');
            $table->timestamp('email_verification_expires_at')->nullable()->after('email_verification_code_hash');
            $table->timestamp('email_verification_sent_at')->nullable()->after('email_verification_expires_at');
            $table->unsignedTinyInteger('email_verification_attempts')->default(0)->after('email_verification_sent_at');
        });

        // Accounts created before OTP verification was introduced remain usable.
        DB::table('customers')->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'email_verified_at',
                'email_verification_code_hash',
                'email_verification_expires_at',
                'email_verification_sent_at',
                'email_verification_attempts',
            ]);
        });
    }
};
