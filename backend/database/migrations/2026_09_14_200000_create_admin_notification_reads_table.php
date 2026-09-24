<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $connection = config('tenancy.database.central_connection') ?: config('database.default', 'central');

        Schema::connection($connection)->create('admin_notification_reads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('notification_key', 120)->index();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'notification_key'], 'uniq_user_noti_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = config('tenancy.database.central_connection') ?: config('database.default', 'central');
        Schema::connection($connection)->dropIfExists('admin_notification_reads');
    }
};
