<?php

namespace App\Http\Middleware;

use App\Models\BlockedIp;
use App\Models\SecurityLog;
use App\Models\SecuritySetting;
use App\Services\GeoIpService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnforceSecurityFirewall
{
    /**
     * Dangerous file extensions to reject.
     */
    protected array $dangerousExtensions = [
        'php', 'php3', 'php4', 'php5', 'phtml', 'phar',
        'exe', 'sh', 'bat', 'cmd', 'pl', 'cgi', 'py',
        'jsp', 'asp', 'aspx', 'vbs', 'scr', 'bin',
    ];

    /**
     * Common SQL Injection Regex Patterns.
     */
    protected array $sqliPatterns = [
        '/union(\s+all)?\s+select/i',
        '/select\s+.*?\s+from\s+/i',
        '/insert\s+into\s+/i',
        '/delete\s+from\s+/i',
        '/drop\s+(table|database|view)/i',
        '/update\s+.*?\s+set\s+/i',
        '/(?:--|#\s|\/\*).*$/m',
        '/\bor\b\s+[\'"]?\d+[\'"]?\s*=\s*[\'"]?\d+[\'"]?/i',
        '/\bor\b\s+[\'"][a-zA-Z0-9]+[\'"]\s*=\s*[\'"][a-zA-Z0-9]+[\'"]/i',
        '/waitfor\s+delay\s+/i',
        '/benchmark\s*\(.*?,.*?\)/i',
        '/sleep\s*\(\s*\d+\s*\)/i',
        '/information_schema/i',
    ];

    /**
     * Common XSS Patterns.
     */
    protected array $xssPatterns = [
        '/<script\b[^>]*>(.*?)<\/script>/is',
        '/javascript\s*:/i',
        '/onerror\s*=/i',
        '/onload\s*=/i',
        '/onclick\s*=/i',
        '/onmouseover\s*=/i',
        '/<iframe\b[^>]*>/i',
        '/document\.cookie/i',
        '/document\.location/i',
    ];

    /**
     * Common Path Traversal & LFI Patterns.
     */
    protected array $traversalPatterns = [
        '~(?:\.\.[\\\/]){2,}~',
        '/\/etc\/passwd/i',
        '/\/proc\/self\/environ/i',
        '/php:\/\/input/i',
        '/php:\/\/filter/i',
        '/data:\/\/text\/plain/i',
    ];

    /**
     * Bad Bot & Scanner Signatures.
     */
    protected array $badBotSignatures = [
        'sqlmap',
        'nikto',
        'masscan',
        'dirbuster',
        'nmap',
        'zgrab',
        'acunetix',
        'havij',
        'wprecon',
        'netsparker',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // 0. Preflight OPTIONS requests must bypass firewall payload checks for CORS
        if ($request->isMethod('OPTIONS')) {
            return $next($request);
        }

        $ip = $request->ip();

        // 1. IP Blacklist Check (Protects Web, API, and Frontend)
        if (BlockedIp::isBlocked($ip)) {
            return $this->blockedResponse(
                $request,
                $ip,
                'Your IP address has been blocked by the security firewall.',
                'SEC-IP-BLACKLIST',
                'ip_blacklist'
            );
        }

        // 2. Global Rate Limiter & DDoS Mitigation Check
        if ($rateLimitResponse = $this->checkRateLimit($request, $ip)) {
            return $rateLimitResponse;
        }

        // 3. Admin IP Whitelist Check
        if (SecuritySetting::getBool('admin_ip_whitelist_enabled', false) && $request->is('admin*')) {
            $whitelistedJson = SecuritySetting::get('admin_whitelisted_ips', '[]');
            $whitelisted = json_decode($whitelistedJson, true) ?: [];

            if (! empty($whitelisted) && ! in_array($ip, $whitelisted, true)) {
                $incident = SecurityLog::logIncident(
                    $ip,
                    'unauthorized_admin_access',
                    'high',
                    'blocked',
                    'Unauthorized IP tried to access admin route: ' . $request->path(),
                    $request
                );

                return $this->blockedResponse(
                    $request,
                    $ip,
                    'Access restricted: Your IP is not authorized to access administrative portals.',
                    $incident->incident_id,
                    'unauthorized_admin_access'
                );
            }
        }

        // 4. Commercial VPN, Anonymous Proxy & Tor Exit Node Filter
        if (SecuritySetting::getBool('block_vpn_proxies', false) && ! GeoIpService::isPrivateIp($ip)) {
            $geo = GeoIpService::lookup($ip, $request);
            if (! empty($geo['is_vpn'])) {
                $incident = SecurityLog::logIncident(
                    $ip,
                    'vpn_proxy_blocked',
                    'medium',
                    'blocked',
                    'Blocked connection from commercial VPN / Datacenter proxy (' . ($geo['isp'] ?? 'Anonymous Proxy') . ')',
                    $request
                );

                return $this->blockedResponse(
                    $request,
                    $ip,
                    'Access restricted: Connections via VPNs, datacenters, or anonymous proxies are not permitted.',
                    $incident->incident_id,
                    'vpn_proxy_blocked'
                );
            }
        }

        // 4. Bad Bot & Exploit Scanner Filter
        if (SecuritySetting::getBool('block_bad_bots', true)) {
            $userAgent = strtolower((string) $request->userAgent());
            foreach ($this->badBotSignatures as $signature) {
                if (str_contains($userAgent, $signature)) {
                    $incident = SecurityLog::logIncident(
                        $ip,
                        'bad_bot_scanner',
                        'high',
                        'blocked',
                        'Vulnerability scanner detected: ' . $userAgent,
                        $request
                    );

                    if (SecuritySetting::get('waf_action', 'block_and_autoban') === 'block_and_autoban') {
                        BlockedIp::block(
                            $ip,
                            'Automated exploit tool detected (' . $signature . ')',
                            'bad_bot',
                            SecuritySetting::getInt('autoban_duration_hours', 24),
                            'Firewall WAF'
                        );
                    }

                    return $this->blockedResponse(
                        $request,
                        $ip,
                        'Automated scanner detected. Access denied.',
                        $incident->incident_id,
                        'bad_bot_scanner'
                    );
                }
            }
        }

        // 5. File Upload Threat Inspection (Web shells, trojans, executables)
        if ($fileThreat = $this->inspectFileUploads($request)) {
            [$threatType, $matchedRule, $fileName] = $fileThreat;

            $incident = SecurityLog::logIncident(
                $ip,
                $threatType,
                'critical',
                'blocked',
                'Malicious upload rejected (' . $matchedRule . '): ' . $fileName,
                $request
            );

            if (SecuritySetting::get('waf_action', 'block_and_autoban') === 'block_and_autoban') {
                BlockedIp::block(
                    $ip,
                    'Malicious file upload attempted (' . $fileName . ')',
                    $threatType,
                    SecuritySetting::getInt('autoban_duration_hours', 24),
                    'Upload Firewall'
                );
            }

            return $this->blockedResponse(
                $request,
                $ip,
                'Dangerous file upload detected and blocked by Security Firewall.',
                $incident->incident_id,
                $threatType
            );
        }

        // 6. WAF Payload Inspection (SQLi, XSS, Path Traversal on All Inputs & Parameters)
        if ($threat = $this->inspectPayloads($request)) {
            [$threatType, $matchedPattern, $matchedValue] = $threat;

            $incident = SecurityLog::logIncident(
                $ip,
                $threatType,
                'critical',
                'blocked',
                'Matched: ' . $matchedPattern . ' in: ' . Str::limit((string) $matchedValue, 300),
                $request
            );

            if (SecuritySetting::get('waf_action', 'block_and_autoban') === 'block_and_autoban') {
                BlockedIp::block(
                    $ip,
                    'WAF: ' . strtoupper($threatType) . ' attempt detected',
                    $threatType,
                    SecuritySetting::getInt('autoban_duration_hours', 24),
                    'Firewall WAF'
                );
            }

            return $this->blockedResponse(
                $request,
                $ip,
                'Malicious request payload detected by Web Application Firewall (' . strtoupper($threatType) . ').',
                $incident->incident_id,
                $threatType
            );
        }

        $response = $next($request);

        // 7. Append Security Headers to Response
        if ($response instanceof Response && ! $response->headers->has('X-Security-Firewall')) {
            $response->headers->set('X-Security-Firewall', 'Active');

            if ($xFrame = SecuritySetting::get('x_frame_options', 'SAMEORIGIN')) {
                $response->headers->set('X-Frame-Options', $xFrame);
            }

            if (SecuritySetting::getBool('x_content_type_options', true)) {
                $response->headers->set('X-Content-Type-Options', 'nosniff');
            }

            if ($referrer = SecuritySetting::get('referrer_policy', 'strict-origin-when-cross-origin')) {
                $response->headers->set('Referrer-Policy', $referrer);
            }

            if (SecuritySetting::getBool('hsts_enabled', false)) {
                $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
            }
        }

        return $response;
    }

    /**
     * Deep-inspect request input for attack payloads.
     */
    protected function inspectPayloads(Request $request): ?array
    {
        $sqliActive = SecuritySetting::getBool('waf_sqli_enabled', true);
        $xssActive = SecuritySetting::getBool('waf_xss_enabled', true);
        $traversalActive = SecuritySetting::getBool('waf_path_traversal_enabled', true);

        if (! $sqliActive && ! $xssActive && ! $traversalActive) {
            return null;
        }

        // Collect inputs to inspect (excluding sensitive password fields to avoid false flags)
        $inputs = $request->except(['password', 'password_confirmation', '_token']);

        // Flatten all query and body inputs
        $flattened = [];
        $this->flattenArray($inputs, $flattened);

        // Also inspect raw query string and URI
        if ($queryString = $request->getQueryString()) {
            $flattened[] = urldecode($queryString);
        }
        $flattened[] = urldecode($request->path());

        foreach ($flattened as $value) {
            if (! is_string($value) || strlen($value) < 3) {
                continue;
            }

            // Test SQLi
            if ($sqliActive) {
                foreach ($this->sqliPatterns as $pattern) {
                    if (preg_match($pattern, $value)) {
                        return ['sql_injection', $pattern, $value];
                    }
                }
            }

            // Test XSS
            if ($xssActive) {
                foreach ($this->xssPatterns as $pattern) {
                    if (preg_match($pattern, $value)) {
                        return ['cross_site_scripting', $pattern, $value];
                    }
                }
            }

            // Test Traversal
            if ($traversalActive) {
                foreach ($this->traversalPatterns as $pattern) {
                    if (preg_match($pattern, $value)) {
                        return ['path_traversal', $pattern, $value];
                    }
                }
            }
        }

        return null;
    }

    protected function flattenArray(array $array, array &$result): void
    {
        foreach ($array as $value) {
            if (is_array($value)) {
                $this->flattenArray($value, $result);
            } elseif (is_scalar($value) || is_object($value)) {
                $result[] = $value;
            }
        }
    }

    /**
     * Inspect uploaded files for malicious scripts and executable web shells.
     */
    protected function inspectFileUploads(Request $request): ?array
    {
        $blockDangerous = SecuritySetting::getBool('block_dangerous_extensions', true);
        $strictMime = SecuritySetting::getBool('strict_file_mime_check', true);

        if (! $blockDangerous && ! $strictMime) {
            return null;
        }

        $files = $request->allFiles();
        if (empty($files)) {
            return null;
        }

        $flattenedFiles = [];
        $this->flattenArray($files, $flattenedFiles);

        foreach ($flattenedFiles as $file) {
            if (! $file instanceof \Illuminate\Http\UploadedFile) {
                continue;
            }

            $clientExtension = strtolower((string) $file->getClientOriginalExtension());
            if ($blockDangerous && in_array($clientExtension, $this->dangerousExtensions, true)) {
                return [
                    'malicious_file_upload',
                    'dangerous_extension:.' . $clientExtension,
                    $file->getClientOriginalName(),
                ];
            }

            if ($strictMime) {
                $mime = strtolower((string) $file->getMimeType());
                $dangerousMimes = [
                    'text/x-php',
                    'application/x-php',
                    'application/x-httpd-php',
                    'application/x-executable',
                    'application/x-sh',
                    'application/x-msdownload',
                ];

                if (in_array($mime, $dangerousMimes, true)) {
                    return [
                        'malicious_file_upload',
                        'dangerous_mime:' . $mime,
                        $file->getClientOriginalName(),
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Check rate limiting and mitigate DDoS flooding.
     */
    protected function checkRateLimit(Request $request, string $ip): ?Response
    {
        // Don't rate limit local loopback
        if ($ip === '127.0.0.1' || $ip === '::1') {
            return null;
        }

        $underAttack = SecuritySetting::getBool('under_attack_mode', false);
        $limit = $underAttack ? 30 : SecuritySetting::getInt('global_rate_limit_per_minute', 120);

        if ($limit <= 0) {
            return null;
        }

        $windowKey = 'sec_req_count_' . md5($ip . '_' . date('YmdHi'));

        try {
            $currentCount = (int) Cache::store('file')->get($windowKey, 0) + 1;
            Cache::store('file')->put($windowKey, $currentCount, 90);

            if ($currentCount > $limit) {
                // Critical flood threshold: auto-ban attacker IP
                if ($currentCount >= ($limit * 2)) {
                    BlockedIp::block(
                        $ip,
                        "High frequency request flood ({$currentCount} req/min, limit {$limit})",
                        'ddos_flood',
                        SecuritySetting::getInt('autoban_duration_hours', 24),
                        'DDoS Firewall'
                    );
                }

                $incident = SecurityLog::logIncident(
                    $ip,
                    'ddos_rate_limit',
                    'high',
                    'rate_limited',
                    "Global request threshold exceeded ({$currentCount}/{$limit} per min)",
                    $request
                );

                if ($request->expectsJson() || $request->is('api/*', 'v1/api/*', 'next/auth/*')) {
                    return response()->json([
                        'success' => false,
                        'error' => 'TooManyRequests',
                        'message' => 'Too many requests. You are being rate limited by security firewall.',
                        'incident_id' => $incident->incident_id,
                        'retry_after' => 60,
                    ], 429, [
                        'Retry-After' => 60,
                        'X-Security-Firewall' => 'RateLimited',
                    ]);
                }

                if (view()->exists('errors.429')) {
                    return response()->view('errors.429', [
                        'ip' => $ip,
                        'incidentId' => $incident->incident_id,
                        'retryAfter' => 60,
                        'limit' => $limit,
                        'currentCount' => $currentCount,
                    ], 429, [
                        'Retry-After' => 60,
                        'X-Security-Firewall' => 'RateLimited',
                    ]);
                }

                return response(
                    "<h1>429 Too Many Requests</h1><p>You have exceeded the allowed request rate. Please slow down and try again shortly.</p><p>Incident: <strong>{$incident->incident_id}</strong></p>",
                    429,
                    [
                        'Retry-After' => 60,
                        'X-Security-Firewall' => 'RateLimited',
                    ]
                );
            }
        } catch (\Throwable $e) {
            // Failsafe: do not block request if cache is unavailable
        }

        return null;
    }

    /**
     * Build blocked response respecting API JSON format vs Web HTML format.
     */
    protected function blockedResponse(
        Request $request,
        string $ip,
        string $reason,
        string $incidentId,
        ?string $threatType = null
    ): Response {
        if ($request->expectsJson() || $request->is('api/*', 'v1/api/*', 'next/auth/*')) {
            return response()->json([
                'success' => false,
                'error' => 'SecurityFirewallBlocked',
                'message' => 'Access Denied by Security Firewall',
                'threat_type' => $threatType,
                'reason' => $reason,
                'incident_id' => $incidentId,
                'ip' => $ip,
            ], 403, [
                'X-Security-Firewall' => 'Blocked',
            ]);
        }

        if (view()->exists('errors.security-blocked')) {
            return response()->view('errors.security-blocked', [
                'ip' => $ip,
                'reason' => $reason,
                'incidentId' => $incidentId,
            ], 403, [
                'X-Security-Firewall' => 'Blocked',
            ]);
        }

        return response(
            "<h1>403 Forbidden</h1><p>{$reason}</p><p>Incident Reference: <strong>{$incidentId}</strong></p><p>IP: {$ip}</p>",
            403,
            ['X-Security-Firewall' => 'Blocked']
        );
    }
}
