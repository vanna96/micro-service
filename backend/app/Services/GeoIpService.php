<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GeoIpService
{
    /**
     * In-memory cache for the current request cycle.
     */
    protected static array $memoryCache = [];

    /**
     * Resolve the effective client IP, checking Cloudflare and reverse proxy headers.
     */
    public static function resolveClientIp(?Request $request = null): string
    {
        $req = $request ?: (function_exists('request') ? request() : null);
        if (! $req) {
            return '127.0.0.1';
        }

        $remoteIp = trim((string) $req->server('REMOTE_ADDR', ''));

        // Only accept a forwarded chain from an internal proxy. Read it from
        // right to left so a caller cannot win by prepending a fake address.
        if (static::isPrivateIp($remoteIp)) {
            $forwarded = array_reverse(array_map('trim', explode(',', (string) $req->header('X-Forwarded-For', ''))));
            foreach ($forwarded as $ip) {
                if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) && ! static::isPrivateIp($ip)) {
                    return $ip;
                }
            }
        }

        // Provider-specific identity headers are opt-in because public clients
        // can otherwise spoof them and bypass country access controls.
        if (config('services.geoip.trust_provider_headers', false)) {
            foreach (['CF-Connecting-IP', 'True-Client-IP', 'X-Real-IP'] as $header) {
                $ip = trim((string) $req->header($header));
                if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return filter_var($remoteIp, FILTER_VALIDATE_IP) ? $remoteIp : '127.0.0.1';
    }

    /**
     * Resolve IP Geolocation and detect VPN/Proxy/Datacenter usage.
     *
     * @return array{country_code: string, country_name: string, city: ?string, isp: ?string, is_vpn: bool, is_tor: bool}
     */
    public static function lookup(string $ip, ?Request $request = null): array
    {
        $ip = trim($ip);

        // Check Reverse Proxy / Cloudflare Geolocation Headers
        $req = $request ?: (function_exists('request') ? request() : null);
        $headerCountry = null;
        if ($req && config('services.geoip.trust_provider_headers', false)) {
            $rawCountry = $req->header('CF-IPCountry')
                ?: $req->header('X-Country-Code')
                ?: $req->header('X-Geo-Country')
                ?: $req->header('X-Forwarded-Country');
            if ($rawCountry && preg_match('/^[A-Za-z]{2}$/', trim($rawCountry))) {
                $headerCountry = strtoupper(trim($rawCountry));
            }
        }

        if (array_key_exists($ip, static::$memoryCache)) {
            $cached = static::$memoryCache[$ip];
            if ($headerCountry) {
                $cached['country_code'] = $headerCountry;
                $cached['country_name'] = static::countryNameFromCode($headerCountry);
            }

            return $cached;
        }

        // 1. Local LAN / Loopback Resolution
        if (static::isPrivateIp($ip)) {
            $country = $headerCountry ?: 'LAN';
            $countryName = $headerCountry ? static::countryNameFromCode($headerCountry) : 'Local Network';
            $data = [
                'country_code' => $country,
                'country_name' => $countryName,
                'city' => $headerCountry ? 'Simulated / Proxy' : 'Internal / LAN',
                'latitude' => null,
                'longitude' => null,
                'isp' => $headerCountry ? 'Proxy Header' : 'Private Subnet',
                'is_vpn' => false,
                'is_tor' => false,
            ];
            static::$memoryCache[$ip] = $data;

            return $data;
        }

        // 2. Check File Cache (Persistent 30-day cache to avoid external rate limits)
        $cacheKey = 'geoip_lookup_'.md5($ip);
        try {
            $cached = Cache::store('file')->get($cacheKey);
            if (is_array($cached) && ! empty($cached['country_code'])) {
                if ($headerCountry) {
                    $cached['country_code'] = $headerCountry;
                    $cached['country_name'] = static::countryNameFromCode($headerCountry);
                }
                if (! array_key_exists('latitude', $cached)) {
                    $coords = static::fallbackCoordinates($cached['country_code'] ?? null, $cached['city'] ?? null);
                    $cached['latitude'] = $coords['lat'];
                    $cached['longitude'] = $coords['lng'];
                }
                static::$memoryCache[$ip] = $cached;

                return $cached;
            }
        } catch (\Throwable $e) {
            // Silently fall through on cache error
        }

        // 3. Check Client Device GPS Headers & Reverse Proxy / Cloudflare Geolocation
        $req = $request ?: (function_exists('request') ? request() : null);
        $clientLat = $req ? ($req->header('X-Client-Latitude') ?: $req->header('X-Geo-Lat') ?: $req->header('CF-IPLatitude')) : null;
        $clientLng = $req ? ($req->header('X-Client-Longitude') ?: $req->header('X-Geo-Lng') ?: $req->header('CF-IPLongitude')) : null;
        $cfCountry = $headerCountry ?: ($req ? ($req->header('CF-IPCountry') ?: $req->header('X-Country-Code')) : null);
        $cfCity = $req ? $req->header('CF-IPCity') : null;
        $cfCoords = static::fallbackCoordinates($cfCountry, $cfCity);

        $geoData = [
            'country_code' => $cfCountry && strlen($cfCountry) === 2 ? strtoupper($cfCountry) : 'UN',
            'country_name' => $cfCountry ? static::countryNameFromCode($cfCountry) : 'Unknown Location',
            'city' => $cfCity ?: 'Unknown City',
            'latitude' => $clientLat !== null ? (float) $clientLat : $cfCoords['lat'],
            'longitude' => $clientLng !== null ? (float) $clientLng : $cfCoords['lng'],
            'isp' => null,
            'is_vpn' => false,
            'is_tor' => false,
        ];

        // 4. Query Fast Geolocation & Datacenter Intelligence API
        try {
            $response = Http::timeout(2)
                ->get("http://ip-api.com/json/{$ip}", [
                    'fields' => 'status,message,country,countryCode,regionName,city,lat,lon,isp,org,as,proxy,hosting',
                ]);

            if ($response->successful() && $response->json('status') === 'success') {
                $payload = $response->json();
                $isProxy = (bool) ($payload['proxy'] ?? false);
                $isHosting = (bool) ($payload['hosting'] ?? false);

                // Datacenter hosting providers (AWS, DigitalOcean, Hetzner, Linode, M247, etc.)
                // indicate VPNs, proxies, and automated bot networks.
                $isVpn = $isProxy || $isHosting;

                $cCode = $headerCountry ?: strtoupper($payload['countryCode'] ?? ($cfCountry ?: 'UN'));
                $cityName = $payload['city'] ?? $payload['regionName'] ?? ($cfCity ?: 'Unknown City');
                $cName = $headerCountry ? static::countryNameFromCode($headerCountry) : ($payload['country'] ?? static::countryNameFromCode($cCode));
                $lat = $clientLat !== null ? (float) $clientLat : (isset($payload['lat']) ? (float) $payload['lat'] : null);
                $lon = $clientLng !== null ? (float) $clientLng : (isset($payload['lon']) ? (float) $payload['lon'] : null);

                if ($lat === null || $lon === null) {
                    $fallback = static::fallbackCoordinates($cCode, $cityName);
                    $lat = $fallback['lat'];
                    $lon = $fallback['lng'];
                }

                $geoData = [
                    'country_code' => $cCode,
                    'country_name' => $cName,
                    'city' => $cityName,
                    'latitude' => $lat,
                    'longitude' => $lon,
                    'isp' => $payload['isp'] ?? $payload['org'] ?? null,
                    'is_vpn' => $isVpn,
                    'is_tor' => $isProxy && str_contains(strtolower($payload['isp'] ?? ''), 'tor'),
                ];
            }
        } catch (\Throwable $e) {
            // Silently continue with fallback if network lookup fails
        }

        // Save to persistent file cache for 30 days
        try {
            Cache::store('file')->put($cacheKey, $geoData, 86400 * 30);
        } catch (\Throwable $e) {
            // Ignore cache write failure
        }

        static::$memoryCache[$ip] = $geoData;

        return $geoData;
    }

    /**
     * Determine if an IP address is a private, loopback, or reserved LAN address.
     */
    public static function isPrivateIp(string $ip): bool
    {
        if (in_array($ip, ['127.0.0.1', '::1', 'localhost'], true)) {
            return true;
        }

        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;
    }

    /**
     * Complete ISO 3166-1 country dictionary with flags and English names.
     */
    public static function allCountries(): array
    {
        static $countries;

        if (is_array($countries)) {
            return $countries;
        }

        $path = resource_path('data/countries.json');
        $names = is_file($path) ? json_decode((string) file_get_contents($path), true) : [];
        $countries = [];

        foreach (is_array($names) ? $names : [] as $code => $name) {
            $code = strtoupper((string) $code);
            if (preg_match('/^[A-Z]{2}$/', $code)) {
                $countries[$code] = [
                    'code' => $code,
                    'name' => (string) $name,
                    'flag' => static::countryFlagEmoji($code),
                ];
            }
        }

        uasort($countries, static fn (array $left, array $right): int => strcasecmp($left['name'], $right['name']));

        return $countries;
    }

    /**
     * Convert an ISO 3166-1 alpha-2 country code to a Unicode flag emoji.
     */
    public static function countryFlagEmoji(?string $countryCode): string
    {
        if (! $countryCode) {
            return '🏳️';
        }

        $code = strtoupper(trim($countryCode));

        if ($code === 'LAN' || $code === 'LOC' || $code === 'UN') {
            return '🌐';
        }

        if (strlen($code) !== 2) {
            return '🏳️';
        }

        // Unicode regional indicator symbols for country flags
        $firstChar = mb_ord($code[0]) - 65 + 0x1F1E6;
        $secondChar = mb_ord($code[1]) - 65 + 0x1F1E6;

        return mb_chr($firstChar).mb_chr($secondChar);
    }

    /**
     * Lookup country name fallback with comprehensive coverage.
     */
    public static function countryNameFromCode(string $code): string
    {
        $code = strtoupper(trim($code));
        $all = static::allCountries();
        if (isset($all[$code])) {
            return $all[$code]['name'];
        }

        return $code === 'LAN' ? 'Local Network' : ($code === 'UN' ? 'Unknown Location' : $code);
    }

    /**
     * Resolve reliable GPS coordinates (latitude, longitude) for known cities and countries.
     *
     * @return array{lat: ?float, lng: ?float}
     */
    public static function fallbackCoordinates(?string $countryCode, ?string $city = null): array
    {
        $cityNorm = $city ? strtolower(trim($city)) : '';
        $cCode = $countryCode ? strtoupper(trim($countryCode)) : '';

        // Exact / Partial City Match Dictionary
        $cityMap = [
            'phnom penh' => ['lat' => 11.556374, 'lng' => 104.928210],
            'siem reap' => ['lat' => 13.367097, 'lng' => 103.844813],
            'battambang' => ['lat' => 13.095730, 'lng' => 103.202202],
            'sihanoukville' => ['lat' => 10.625295, 'lng' => 103.523396],
            'bangkok' => ['lat' => 13.756331, 'lng' => 100.501765],
            'chiang mai' => ['lat' => 18.788344, 'lng' => 98.985301],
            'frankfurt' => ['lat' => 50.110924, 'lng' => 8.682127],
            'berlin' => ['lat' => 52.520008, 'lng' => 13.404954],
            'munich' => ['lat' => 48.135125, 'lng' => 11.581981],
            'singapore' => ['lat' => 1.352083, 'lng' => 103.819836],
            'north bergen' => ['lat' => 40.804268, 'lng' => -74.012085],
            'new york' => ['lat' => 40.712776, 'lng' => -74.005974],
            'los angeles' => ['lat' => 34.052235, 'lng' => -118.243683],
            'san francisco' => ['lat' => 37.774929, 'lng' => -122.419418],
            'chicago' => ['lat' => 41.878113, 'lng' => -87.629799],
            'seattle' => ['lat' => 47.606209, 'lng' => -122.332071],
            'tokyo' => ['lat' => 35.676192, 'lng' => 139.650311],
            'osaka' => ['lat' => 34.693738, 'lng' => 135.502165],
            'london' => ['lat' => 51.507351, 'lng' => -0.127758],
            'paris' => ['lat' => 48.856613, 'lng' => 2.352222],
            'sydney' => ['lat' => -33.868820, 'lng' => 151.209296],
            'melbourne' => ['lat' => -37.813628, 'lng' => 144.963058],
            'toronto' => ['lat' => 43.653225, 'lng' => -79.383186],
            'vancouver' => ['lat' => 49.282729, 'lng' => -123.120738],
            'amsterdam' => ['lat' => 52.367573, 'lng' => 4.904139],
            'seoul' => ['lat' => 37.566536, 'lng' => 126.977966],
            'hong kong' => ['lat' => 22.319303, 'lng' => 114.169361],
            'kuala lumpur' => ['lat' => 3.139003, 'lng' => 101.686852],
            'jakarta' => ['lat' => -6.208763, 'lng' => 106.845599],
            'ho chi minh' => ['lat' => 10.823099, 'lng' => 106.629664],
            'hanoi' => ['lat' => 21.028511, 'lng' => 105.854167],
            'manila' => ['lat' => 14.599512, 'lng' => 120.984222],
            'dubai' => ['lat' => 25.204849, 'lng' => 55.270782],
            'mumbai' => ['lat' => 19.076090, 'lng' => 72.877426],
            'new delhi' => ['lat' => 28.613939, 'lng' => 77.209021],
            'zurich' => ['lat' => 47.376888, 'lng' => 8.541694],
        ];

        foreach ($cityMap as $name => $coords) {
            if (str_contains($cityNorm, $name)) {
                return $coords;
            }
        }

        // Country Default Coordinates
        $countryMap = [
            'KH' => ['lat' => 11.556374, 'lng' => 104.928210], // Phnom Penh
            'TH' => ['lat' => 13.756331, 'lng' => 100.501765], // Bangkok
            'DE' => ['lat' => 50.110924, 'lng' => 8.682127],   // Frankfurt
            'SG' => ['lat' => 1.352083, 'lng' => 103.819836],  // Singapore
            'US' => ['lat' => 40.712776, 'lng' => -74.005974], // New York
            'VN' => ['lat' => 10.823099, 'lng' => 106.629664], // Ho Chi Minh
            'MY' => ['lat' => 3.139003, 'lng' => 101.686852],  // Kuala Lumpur
            'JP' => ['lat' => 35.676192, 'lng' => 139.650311], // Tokyo
            'GB' => ['lat' => 51.507351, 'lng' => -0.127758],  // London
            'FR' => ['lat' => 48.856613, 'lng' => 2.352222],   // Paris
            'AU' => ['lat' => -33.868820, 'lng' => 151.209296], // Sydney
            'CA' => ['lat' => 43.653225, 'lng' => -79.383186], // Toronto
            'NL' => ['lat' => 52.367573, 'lng' => 4.904139],   // Amsterdam
            'KR' => ['lat' => 37.566536, 'lng' => 126.977966], // Seoul
            'HK' => ['lat' => 22.319303, 'lng' => 114.169361], // Hong Kong
            'PH' => ['lat' => 14.599512, 'lng' => 120.984222], // Manila
            'ID' => ['lat' => -6.208763, 'lng' => 106.845599], // Jakarta
            'CN' => ['lat' => 39.904200, 'lng' => 116.407396], // Beijing
            'IN' => ['lat' => 28.613939, 'lng' => 77.209021],  // New Delhi
            'AE' => ['lat' => 25.204849, 'lng' => 55.270782],  // Dubai
            'CH' => ['lat' => 47.376888, 'lng' => 8.541694],   // Zurich
        ];

        return $countryMap[$cCode] ?? ['lat' => null, 'lng' => null];
    }

    /**
     * Clear memory cache (useful in tests).
     */
    public static function clearMemoryCache(): void
    {
        static::$memoryCache = [];
    }
}
