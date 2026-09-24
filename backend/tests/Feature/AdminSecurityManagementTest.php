<?php

namespace Tests\Feature;

use App\Models\BlockedIp;
use App\Models\SecurityLog;
use App\Models\SecuritySetting;
use App\Models\User;
use App\Services\GeoIpService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminSecurityManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        try {
            Cache::store('file')->flush();
        } catch (\Throwable $e) {
        }
        BlockedIp::clearMemoryCache();
        GeoIpService::clearMemoryCache();
        BlockedIp::query()->delete();
        SecurityLog::query()->delete();
        SecuritySetting::set('admin_ip_whitelist_enabled', '0');
        SecuritySetting::set('admin_whitelisted_ips', '[]');
        SecuritySetting::set('block_vpn_proxies', '0');
        SecuritySetting::set('waf_sqli_enabled', '1');
        SecuritySetting::set('waf_xss_enabled', '1');
        SecuritySetting::set('waf_path_traversal_enabled', '1');
        SecuritySetting::set('waf_action', 'block_and_autoban');
        SecuritySetting::set('honeypot_traps_enabled', '1');
    }

    protected function tearDown(): void
    {
        SecuritySetting::set('admin_ip_whitelist_enabled', '0');
        SecuritySetting::set('admin_whitelisted_ips', '[]');
        SecuritySetting::set('block_vpn_proxies', '0');
        SecuritySetting::set('global_rate_limit_per_minute', '120');
        Cache::flush();
        try {
            Cache::store('file')->flush();
        } catch (\Throwable $e) {
        }
        BlockedIp::clearMemoryCache();
        GeoIpService::clearMemoryCache();
        parent::tearDown();
    }

    public function test_security_dashboard_accessible_by_administrator(): void
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)
            ->get(route('admin.security.index'));

        $response->assertOk()
            ->assertViewIs('admin.security.index')
            ->assertSee('Security Center')
            ->assertSee('Web Application Firewall (WAF)')
            ->assertSee('Total Threats Stopped')
            ->assertSee('DDoS & Rate Limits')
            ->assertSee('Firewall & IP Rules');
    }

    public function test_security_dashboard_renders_datatables_with_database_records(): void
    {
        $admin = $this->createAdminUser();

        SecurityLog::query()->create([
            'incident_id' => 'SEC-TEST-9988',
            'ip_address' => '198.51.100.222',
            'threat_type' => 'sql_injection',
            'severity' => 'critical',
            'action_taken' => 'blocked',
            'request_url' => '/api/v1/orders',
            'request_method' => 'POST',
            'payload' => 'UNION SELECT 1,2,3',
            'user_agent' => 'Mozilla/5.0 TestBot',
        ]);

        BlockedIp::block('198.51.100.222', 'Test manual block', 'sql_injection', 24, 'Admin');

        $response = $this->actingAs($admin)
            ->get(route('admin.security.index', ['tab' => 'monitor']));

        $response->assertOk()
            ->assertSee('datatable-security-logs')
            ->assertSee('datatable-blocked-ips')
            ->assertSee('SEC-TEST-9988')
            ->assertSee('198.51.100.222')
            ->assertSee('UNION SELECT 1,2,3')
            ->assertSee('dataTables.bootstrap4.min.js');
    }

    public function test_security_settings_can_be_updated(): void
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)
            ->put(route('admin.security.settings.update'), [
                'tab' => 'waf',
                'waf_sqli_enabled' => '1',
                'waf_xss_enabled' => '1',
                'waf_path_traversal_enabled' => '1',
                'waf_action' => 'block_only',
                'honeypot_traps_enabled' => '1',
                'under_attack_mode' => '1',
                'global_rate_limit_per_minute' => '90',
                'block_bad_bots' => '1',
                'autoban_duration_hours' => '48',
            ]);

        $response->assertRedirect(route('admin.security.index', ['tab' => 'waf']))
            ->assertSessionHas('status', 'Security settings updated successfully.');

        $this->assertEquals('block_only', SecuritySetting::get('waf_action'));
        $this->assertEquals('1', SecuritySetting::get('under_attack_mode'));
        $this->assertEquals('90', SecuritySetting::get('global_rate_limit_per_minute'));
        $this->assertEquals('48', SecuritySetting::get('autoban_duration_hours'));
    }

    public function test_admin_can_manually_block_ip(): void
    {
        $admin = $this->createAdminUser();
        $targetIp = '198.51.100.77';

        $response = $this->actingAs($admin)
            ->post(route('admin.security.block-ip'), [
                'ip_address' => $targetIp,
                'reason' => 'Automated brute force testing',
                'duration_hours' => 24,
            ]);

        $response->assertRedirect(route('admin.security.index', ['tab' => 'firewall']))
            ->assertSessionHas('status');

        $this->assertTrue(BlockedIp::isBlocked($targetIp));
        $this->assertDatabaseHas('blocked_ips', [
            'ip_address' => $targetIp,
            'reason' => 'Automated brute force testing',
        ]);
    }

    public function test_blocked_ip_receives_403_forbidden(): void
    {
        $blockedIp = '198.51.100.99';
        BlockedIp::block($blockedIp, 'Malicious attacker test', 'manual', 24, 'Admin');

        $response = $this->withServerVariables(['REMOTE_ADDR' => $blockedIp])
            ->get('/');

        $response->assertStatus(403);
        $this->assertStringContainsString('Access Denied by Security Firewall', $response->getContent());
    }

    public function test_admin_can_unblock_ip(): void
    {
        $admin = $this->createAdminUser();
        $blockedIp = '198.51.100.111';
        $record = BlockedIp::block($blockedIp, 'Temporary block', 'manual', 1, 'Admin');

        $this->assertTrue(BlockedIp::isBlocked($blockedIp));

        $response = $this->actingAs($admin)
            ->delete(route('admin.security.unblock-ip', ['id' => $record->id]));

        $response->assertRedirect(route('admin.security.index', ['tab' => 'firewall']))
            ->assertSessionHas('status');

        $this->assertFalse(BlockedIp::isBlocked($blockedIp));
        $this->assertDatabaseMissing('blocked_ips', [
            'ip_address' => $blockedIp,
        ]);
    }

    public function test_sql_injection_payload_is_blocked_and_logged(): void
    {
        SecuritySetting::set('waf_sqli_enabled', '1');
        SecuritySetting::set('waf_action', 'block_and_autoban');
        $attackerIp = '203.0.113.123';

        $response = $this->withServerVariables(['REMOTE_ADDR' => $attackerIp])
            ->get('/?search=1%27%20UNION%20SELECT%20username,password%20FROM%20users--');

        $response->assertStatus(403);

        $this->assertDatabaseHas('security_logs', [
            'ip_address' => $attackerIp,
            'threat_type' => 'sql_injection',
            'action_taken' => 'blocked',
        ]);

        $this->assertTrue(BlockedIp::isBlocked($attackerIp));
    }

    public function test_honeypot_route_triggers_auto_ban(): void
    {
        SecuritySetting::set('honeypot_traps_enabled', '1');
        $scannerIp = '203.0.113.199';

        $response = $this->withServerVariables(['REMOTE_ADDR' => $scannerIp])
            ->get('/.env');

        $response->assertStatus(403);

        $this->assertDatabaseHas('security_logs', [
            'ip_address' => $scannerIp,
            'threat_type' => 'honeypot_trap',
            'action_taken' => 'auto_banned',
        ]);

        $this->assertTrue(BlockedIp::isBlocked($scannerIp));
    }

    public function test_admin_whitelist_restricts_unauthorized_ip(): void
    {
        $admin = $this->createAdminUser();

        SecuritySetting::set('admin_ip_whitelist_enabled', '1');
        SecuritySetting::set('admin_whitelisted_ips', json_encode(['192.168.1.50']));

        // 1. Unauthorized IP is blocked from admin route
        $responseUnauthorized = $this->actingAs($admin)
            ->withServerVariables(['REMOTE_ADDR' => '192.168.1.99'])
            ->get(route('admin.security.index'));

        $responseUnauthorized->assertStatus(403);

        // 2. Authorized IP can access admin route
        $responseAuthorized = $this->actingAs($admin)
            ->withServerVariables(['REMOTE_ADDR' => '192.168.1.50'])
            ->get(route('admin.security.index'));

        $responseAuthorized->assertOk();
    }

    public function test_repeated_failed_logins_triggers_auto_ban(): void
    {
        SecuritySetting::set('autoban_failed_logins_enabled', '1');
        SecuritySetting::set('autoban_login_threshold', '3');
        $attackerIp = '198.51.100.88';

        // 1st failed attempt
        $this->withServerVariables(['REMOTE_ADDR' => $attackerIp])
            ->post('/admin/login', [
                'username' => 'admin',
                'password' => 'wrong-pass-1',
            ]);
        $this->assertFalse(BlockedIp::isBlocked($attackerIp));

        // 2nd failed attempt
        $this->withServerVariables(['REMOTE_ADDR' => $attackerIp])
            ->post('/admin/login', [
                'username' => 'admin',
                'password' => 'wrong-pass-2',
            ]);
        $this->assertFalse(BlockedIp::isBlocked($attackerIp));

        // 3rd failed attempt reaches threshold (3) -> auto-ban!
        $res3 = $this->withServerVariables(['REMOTE_ADDR' => $attackerIp])
            ->post('/admin/login', [
                'username' => 'admin',
                'password' => 'wrong-pass-3',
            ]);

        $res3->assertStatus(403);
        $this->assertTrue(BlockedIp::isBlocked($attackerIp));
        $this->assertDatabaseHas('security_logs', [
            'ip_address' => $attackerIp,
            'threat_type' => 'brute_force_login',
            'action_taken' => 'auto_banned',
        ]);
    }

    public function test_api_requests_blocked_by_firewall_receive_json_response(): void
    {
        SecuritySetting::set('waf_sqli_enabled', '1');
        SecuritySetting::set('waf_action', 'block_and_autoban');
        $attackerIp = '203.0.113.155';

        $response = $this->withServerVariables(['REMOTE_ADDR' => $attackerIp])
            ->getJson('/v1/api/tenant-host?search=1%27%20UNION%20SELECT%20username,password%20FROM%20users--');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error' => 'SecurityFirewallBlocked',
                'threat_type' => 'sql_injection',
            ]);

        $this->assertTrue(BlockedIp::isBlocked($attackerIp));
    }

    public function test_blocked_ip_on_api_receives_json_403(): void
    {
        $blockedIp = '198.51.100.177';
        BlockedIp::block($blockedIp, 'API Attack test', 'manual', 24, 'Admin');

        $response = $this->withServerVariables(['REMOTE_ADDR' => $blockedIp])
            ->getJson('/v1/api/tenant-host');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error' => 'SecurityFirewallBlocked',
                'threat_type' => 'ip_blacklist',
                'ip' => $blockedIp,
            ]);
    }

    public function test_dangerous_file_upload_is_blocked_and_logged(): void
    {
        SecuritySetting::set('block_dangerous_extensions', '1');
        SecuritySetting::set('waf_action', 'block_and_autoban');
        $attackerIp = '203.0.113.188';

        $fakeShell = UploadedFile::fake()->create('webshell.php', 10, 'application/x-php');

        $response = $this->withServerVariables(['REMOTE_ADDR' => $attackerIp])
            ->post('/v1/api/pos/display/sync', [
                'avatar' => $fakeShell,
            ], [
                'Accept' => 'application/json',
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error' => 'SecurityFirewallBlocked',
                'threat_type' => 'malicious_file_upload',
            ]);

        $this->assertTrue(BlockedIp::isBlocked($attackerIp));
        $this->assertDatabaseHas('security_logs', [
            'ip_address' => $attackerIp,
            'threat_type' => 'malicious_file_upload',
        ]);
    }

    public function test_security_verify_ip_and_client_config_endpoints_accessible(): void
    {
        $testIp = '203.0.113.166';
        BlockedIp::block($testIp, 'Blocked for test');

        $resVerify = $this->getJson('/v1/api/security/verify-ip?ip=' . $testIp);
        $resVerify->assertOk()
            ->assertJson([
                'ip' => $testIp,
                'blocked' => true,
                'firewall_active' => true,
            ]);

        $resClean = $this->getJson('/v1/api/security/verify-ip?ip=192.0.2.1');
        $resClean->assertOk()
            ->assertJson([
                'blocked' => false,
            ]);

        $resConfig = $this->getJson('/v1/api/security/client-config');
        $resConfig->assertOk()
            ->assertJsonStructure([
                'anti_inspection_enabled',
                'disable_right_click',
                'disable_devtools_keys',
                'under_attack_mode',
            ]);
    }

    public function test_global_rate_limiter_throttles_high_frequency_requests(): void
    {
        SecuritySetting::set('global_rate_limit_per_minute', '3');
        SecuritySetting::set('under_attack_mode', '0');
        $flooderIp = '203.0.113.199';

        // Requests 1, 2, 3 should succeed
        for ($i = 1; $i <= 3; $i++) {
            $res = $this->withServerVariables(['REMOTE_ADDR' => $flooderIp])
                ->getJson('/v1/api/tenant-host');
            $this->assertNotEquals(429, $res->status(), "Request {$i} should not be throttled.");
        }

        // Request 4 exceeds the limit of 3
        $resThrottled = $this->withServerVariables(['REMOTE_ADDR' => $flooderIp])
            ->getJson('/v1/api/tenant-host');

        $resThrottled->assertStatus(429)
            ->assertJson([
                'success' => false,
                'error' => 'TooManyRequests',
            ]);

        $this->assertDatabaseHas('security_logs', [
            'ip_address' => $flooderIp,
            'threat_type' => 'ddos_rate_limit',
        ]);
    }

    public function test_geoip_service_converts_country_code_to_flag_emoji(): void
    {
        $this->assertEquals('🇺🇸', GeoIpService::countryFlagEmoji('US'));
        $this->assertEquals('🇰🇭', GeoIpService::countryFlagEmoji('KH'));
        $this->assertEquals('🌐', GeoIpService::countryFlagEmoji('LAN'));
        $this->assertEquals('🌐', GeoIpService::countryFlagEmoji('LOC'));
        $this->assertEquals('🏳️', GeoIpService::countryFlagEmoji(null));
        $this->assertEquals('Cambodia', GeoIpService::countryNameFromCode('KH'));
        $this->assertEquals('United States', GeoIpService::countryNameFromCode('US'));
    }

    public function test_geoip_service_resolves_private_and_public_ips(): void
    {
        // 1. Loopback / Private LAN IP
        $privateResult = GeoIpService::lookup('127.0.0.1');
        $this->assertEquals('LAN', $privateResult['country_code']);
        $this->assertEquals('Local Network', $privateResult['country_name']);
        $this->assertFalse($privateResult['is_vpn']);

        $private192 = GeoIpService::lookup('192.168.1.100');
        $this->assertEquals('LAN', $private192['country_code']);

        // 2. Public IP with cached data
        $testPublicIp = '198.51.100.42';
        Cache::store('file')->put('geoip_lookup_' . md5($testPublicIp), [
            'country_code' => 'KH',
            'country_name' => 'Cambodia',
            'city' => 'Phnom Penh',
            'isp' => 'Smart Axiata',
            'is_vpn' => false,
            'is_tor' => false,
        ], 3600);

        $cachedResult = GeoIpService::lookup($testPublicIp);
        $this->assertEquals('KH', $cachedResult['country_code']);
        $this->assertEquals('Cambodia', $cachedResult['country_name']);
        $this->assertEquals('Phnom Penh', $cachedResult['city']);
        $this->assertFalse($cachedResult['is_vpn']);
    }

    public function test_security_log_persists_and_displays_geolocation_and_vpn_flag(): void
    {
        $admin = $this->createAdminUser();
        $targetIp = '198.51.100.99';

        // Preload cache for target IP
        Cache::store('file')->put('geoip_lookup_' . md5($targetIp), [
            'country_code' => 'KH',
            'country_name' => 'Cambodia',
            'city' => 'Phnom Penh',
            'isp' => 'Ezecom',
            'is_vpn' => true,
            'is_tor' => false,
        ], 3600);

        $log = SecurityLog::logIncident(
            $targetIp,
            'sql_injection',
            'critical',
            'blocked',
            'SELECT * FROM users'
        );

        $this->assertEquals('KH', $log->country_code);
        $this->assertEquals('Cambodia', $log->country_name);
        $this->assertEquals('Phnom Penh', $log->city);
        $this->assertTrue($log->is_vpn);
        $this->assertEquals('🇰🇭', $log->flag_emoji);
        $this->assertStringContainsString('🇰🇭 Cambodia (Phnom Penh)', $log->location_display);
        $this->assertNotNull($log->google_maps_url);
        $this->assertStringContainsString('https://www.google.com/maps?q=11.556374,104.928210', $log->google_maps_url);
        $this->assertNotNull($log->coordinates_display);
        $this->assertStringContainsString('11.5564° N', $log->coordinates_display);

        // Verify private LAN log has null google_maps_url and can have tenant_id
        $lanLog = SecurityLog::logIncident('192.168.1.10', 'brute_force', 'low', 'warned', null, null, 'tenant-alpha');
        $this->assertNull($lanLog->google_maps_url);
        $this->assertEquals('tenant-alpha', $lanLog->tenant_id);

        // Verify monitor dashboard displays the Location column, VPN badge, Google Maps link, and Target Tenant
        $response = $this->actingAs($admin)
            ->get(route('admin.security.index', ['tab' => 'monitor']));

        $response->assertOk()
            ->assertSee('Location &amp; Network', false)
            ->assertSee('Store Name')
            ->assertSee('tenant-alpha')
            ->assertSee('Central (All Stores)')
            ->assertSee('🇰🇭')
            ->assertSee('KH')
            ->assertSee('Phnom Penh')
            ->assertSee('VPN')
            ->assertSee('google.com/maps?q=11.556374,104.928210', false);
    }

    public function test_commercial_vpn_and_datacenter_proxy_blocking_when_enabled(): void
    {
        SecuritySetting::set('block_vpn_proxies', '1');
        $vpnIp = '203.0.113.88';

        // Preload cache as a commercial datacenter / VPN IP
        Cache::store('file')->put('geoip_lookup_' . md5($vpnIp), [
            'country_code' => 'US',
            'country_name' => 'United States',
            'city' => 'Ashburn',
            'isp' => 'DigitalOcean Datacenter',
            'is_vpn' => true,
            'is_tor' => false,
        ], 3600);

        $response = $this->withServerVariables(['REMOTE_ADDR' => $vpnIp])
            ->getJson('/v1/api/tenant-host');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error' => 'SecurityFirewallBlocked',
                'threat_type' => 'vpn_proxy_blocked',
            ]);

        $this->assertDatabaseHas('security_logs', [
            'ip_address' => $vpnIp,
            'threat_type' => 'vpn_proxy_blocked',
            'is_vpn' => 1,
        ]);
    }

    public function test_vpn_allowed_when_blocking_toggle_disabled(): void
    {
        SecuritySetting::set('block_vpn_proxies', '0');
        $vpnIp = '203.0.113.89';

        Cache::store('file')->put('geoip_lookup_' . md5($vpnIp), [
            'country_code' => 'SG',
            'country_name' => 'Singapore',
            'city' => 'Singapore',
            'isp' => 'NordVPN / M247',
            'is_vpn' => true,
            'is_tor' => false,
        ], 3600);

        $response = $this->withServerVariables(['REMOTE_ADDR' => $vpnIp])
            ->getJson('/v1/api/tenant-host');

        // Request should pass through the VPN check (does not return 403 vpn_proxy_blocked)
        $this->assertNotEquals(403, $response->status());
    }

    public function test_vpn_blocking_toggle_can_be_updated_via_admin_settings(): void
    {
        $admin = $this->createAdminUser();

        // 1. Enable VPN blocking
        $responseEnable = $this->actingAs($admin)
            ->put(route('admin.security.settings.update'), [
                'tab' => 'firewall',
                'block_vpn_proxies' => '1',
            ]);

        $responseEnable->assertRedirect()
            ->assertSessionHas('status');

        $this->assertEquals('1', SecuritySetting::get('block_vpn_proxies'));

        // 2. Disable VPN blocking (omitted checkbox)
        $responseDisable = $this->actingAs($admin)
            ->put(route('admin.security.settings.update'), [
                'tab' => 'firewall',
            ]);

        $responseDisable->assertRedirect()
            ->assertSessionHas('status');

        $this->assertEquals('0', SecuritySetting::get('block_vpn_proxies'));
    }

    public function test_admin_can_clear_security_logs_and_stay_empty(): void
    {
        $admin = $this->createAdminUser();

        // Seed 2 logs
        SecurityLog::query()->create([
            'incident_id' => 'SEC-DEL-01',
            'ip_address' => '1.1.1.1',
            'threat_type' => 'sql_injection',
            'severity' => 'critical',
            'action_taken' => 'blocked',
            'request_url' => '/api/v1/test',
            'request_method' => 'POST',
        ]);
        SecurityLog::query()->create([
            'incident_id' => 'SEC-DEL-02',
            'ip_address' => '2.2.2.2',
            'threat_type' => 'honeypot_trap',
            'severity' => 'high',
            'action_taken' => 'blocked',
            'request_url' => '/api/v1/honeypot',
            'request_method' => 'GET',
        ]);

        $this->assertEquals(2, SecurityLog::count());

        // Admin submits delete request
        $response = $this->actingAs($admin)
            ->delete(route('admin.security.clear-logs'));

        $response->assertRedirect(route('admin.security.index', ['tab' => 'monitor']))
            ->assertSessionHas('status', 'Security incident logs have been cleared.');

        // Verify count is 0 in database
        $this->assertEquals(0, SecurityLog::count());

        // Follow redirect to index page - verify it does NOT auto-reseed logs!
        $pageResponse = $this->actingAs($admin)
            ->get(route('admin.security.index', ['tab' => 'monitor']));

        $pageResponse->assertOk()
            ->assertSee('Security incident logs have been cleared.');

        // Must still be 0!
        $this->assertEquals(0, SecurityLog::count());
    }

    public function test_admin_can_load_sample_security_logs(): void
    {
        $admin = $this->createAdminUser();
        SecurityLog::query()->delete();
        $this->assertEquals(0, SecurityLog::count());

        $response = $this->actingAs($admin)
            ->post(route('admin.security.seed-samples'));

        $response->assertRedirect(route('admin.security.index', ['tab' => 'monitor']))
            ->assertSessionHas('status', 'Sample security incidents loaded successfully.');

        $this->assertGreaterThan(0, SecurityLog::count());
    }

    public function test_cors_settings_can_be_updated_and_applied(): void
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)
            ->put(route('admin.security.settings.update'), [
                'tab' => 'cors',
                'cors_enabled' => '1',
                'cors_allowed_origins' => "https://frontend.example.com\nhttps://pos.example.com",
                'cors_supports_credentials' => '1',
                'cors_allowed_methods' => 'GET, POST, PUT, DELETE, OPTIONS',
                'cors_max_age' => '3600',
            ]);

        $response->assertRedirect(route('admin.security.index', ['tab' => 'cors']))
            ->assertSessionHas('status', 'Security settings updated successfully.');

        $this->assertEquals('1', SecuritySetting::get('cors_enabled'));
        $this->assertStringContainsString('https://frontend.example.com', SecuritySetting::get('cors_allowed_origins'));
        $this->assertEquals('3600', SecuritySetting::get('cors_max_age'));
    }

    public function test_live_threat_triggers_telegram_alert(): void
    {
        SecuritySetting::set('telegram_security_alerts_enabled', '1');
        SecuritySetting::set('telegram_security_min_severity', 'medium');
        SecuritySetting::set('telegram_security_bot_token', '123456:FAKE-BOT-TOKEN');
        SecuritySetting::set('telegram_security_chat_id', '-100987654321');

        \Illuminate\Support\Facades\Http::fake([
            'api.telegram.org/*' => \Illuminate\Support\Facades\Http::response(['ok' => true], 200),
        ]);

        $incident = SecurityLog::logIncident(
            '198.51.100.22',
            'sql_injection',
            'high',
            'blocked_and_autobanned',
            "' OR 1=1 --"
        );

        $this->assertNotNull($incident);
        $this->assertEquals('sql_injection', $incident->threat_type);

        \Illuminate\Support\Facades\Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
            return str_contains($request->url(), 'api.telegram.org/bot123456:FAKE-BOT-TOKEN/sendMessage')
                && str_contains($request['text'], 'Live Threat & Incident Alert')
                && str_contains($request['text'], '198.51.100.22')
                && str_contains($request['text'], '├ <b>Threat :</b>');
        });
    }

    private function createAdminUser(array $attributes = []): User
    {
        return User::query()->create(array_merge([
            'username' => 'sec-admin-' . uniqid(),
            'name' => 'Security Admin',
            'email' => 'sec-admin-' . uniqid() . '@example.com',
            'password' => Hash::make('secret123'),
            'role' => 'admin',
            'status' => 'Active',
        ], $attributes));
    }
}
