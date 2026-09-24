<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('blocked_ips')) {
            Schema::create('blocked_ips', function (Blueprint $table) {
                $table->id();
                $table->string('ip_address', 45)->unique();
                $table->string('reason', 255)->nullable();
                $table->string('threat_type', 50)->default('manual');
                $table->string('blocked_by', 100)->nullable();
                $table->unsignedInteger('strike_count')->default(1);
                $table->dateTime('expires_at')->nullable()->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('security_logs')) {
            Schema::create('security_logs', function (Blueprint $table) {
                $table->id();
                $table->string('incident_id', 40)->index();
                $table->string('ip_address', 45)->index();
                $table->string('threat_type', 50)->index();
                $table->string('severity', 20)->default('medium');
                $table->string('request_method', 10)->default('GET');
                $table->text('request_url');
                $table->text('user_agent')->nullable();
                $table->longText('payload')->nullable();
                $table->string('action_taken', 50)->default('blocked');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('security_settings')) {
            Schema::create('security_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key', 100)->unique();
                $table->longText('value')->nullable();
                $table->timestamps();
            });

            $defaults = [
                'waf_sqli_enabled' => '1',
                'waf_xss_enabled' => '1',
                'waf_path_traversal_enabled' => '1',
                'waf_action' => 'block_and_autoban',
                'under_attack_mode' => '0',
                'global_rate_limit_per_minute' => '120',
                'block_bad_bots' => '1',
                'autoban_failed_logins_enabled' => '1',
                'autoban_login_threshold' => '5',
                'autoban_duration_hours' => '24',
                'admin_ip_whitelist_enabled' => '0',
                'admin_whitelisted_ips' => '[]',
                'strict_file_mime_check' => '1',
                'block_dangerous_extensions' => '1',
                'anti_inspection_enabled' => '0',
                'disable_right_click' => '0',
                'disable_devtools_keys' => '0',
                'hsts_enabled' => '0',
                'x_frame_options' => 'SAMEORIGIN',
                'x_content_type_options' => '1',
                'referrer_policy' => 'strict-origin-when-cross-origin',
                'honeypot_traps_enabled' => '1',
            ];

            $now = now();
            $rows = [];
            foreach ($defaults as $key => $val) {
                $rows[] = [
                    'key' => $key,
                    'value' => (string) $val,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('security_settings')->insert($rows);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('security_settings');
        Schema::dropIfExists('security_logs');
        Schema::dropIfExists('blocked_ips');
    }
};
