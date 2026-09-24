<?php

namespace App\Models;

use App\Services\GeoIpService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SecurityLog extends Model
{
    use HasFactory;

    protected $table = 'security_logs';

    protected $fillable = [
        'incident_id',
        'tenant_id',
        'store_name',
        'ip_address',
        'country_code',
        'country_name',
        'city',
        'latitude',
        'longitude',
        'is_vpn',
        'isp',
        'threat_type',
        'severity',
        'request_method',
        'request_url',
        'user_agent',
        'payload',
        'action_taken',
    ];

    protected $casts = [
        'is_vpn' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->setConnection(config('tenancy.database.central_connection') ?: config('database.default', 'central'));
    }

    public function tenant(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * Get the friendly store name (e.g. "Meas Rechna", "b001", or "Central / Global")
     */
    public function getStoreNameAttribute(): string
    {
        if (! empty($this->attributes['store_name'])) {
            return $this->attributes['store_name'];
        }

        if ($this->tenant_id) {
            $t = $this->tenant ?: Tenant::find($this->tenant_id);
            if ($t) {
                return $t->general_settings['store_name'] ?? $t->db_name ?? (string) $t->id;
            }

            return (string) $this->tenant_id;
        }

        return 'Central / Global (All Stores)';
    }

    public function getTenantDisplayAttribute(): string
    {
        return $this->store_name;
    }

    public function getFlagEmojiAttribute(): string
    {
        return GeoIpService::countryFlagEmoji($this->country_code);
    }

    public function getLocationDisplayAttribute(): string
    {
        $flag = $this->flag_emoji;
        $country = $this->country_name ?: $this->country_code ?: 'Unknown';
        $city = $this->city && $this->city !== 'Unknown City' ? " ({$this->city})" : '';

        return "{$flag} {$country}{$city}";
    }

    /**
     * Get effective GPS coordinates [lat, lng] from database or reliable fallback.
     *
     * @return array{lat: ?float, lng: ?float}|null
     */
    public function getEffectiveCoordinatesAttribute(): ?array
    {
        if ($this->country_code === 'LAN' || GeoIpService::isPrivateIp($this->ip_address)) {
            return null;
        }

        if ($this->latitude !== null && $this->longitude !== null) {
            return [
                'lat' => (float) $this->latitude,
                'lng' => (float) $this->longitude,
            ];
        }

        $fallback = GeoIpService::fallbackCoordinates($this->country_code, $this->city);
        if ($fallback['lat'] !== null && $fallback['lng'] !== null) {
            return $fallback;
        }

        return null;
    }

    /**
     * Human-readable coordinates string e.g. "11.5564° N, 104.9282° E"
     */
    public function getCoordinatesDisplayAttribute(): ?string
    {
        $coords = $this->effective_coordinates;
        if (! $coords || $coords['lat'] === null || $coords['lng'] === null) {
            return null;
        }

        $lat = $coords['lat'];
        $lng = $coords['lng'];

        $latDir = $lat >= 0 ? 'N' : 'S';
        $lngDir = $lng >= 0 ? 'E' : 'W';

        return sprintf('%.4f° %s, %.4f° %s', abs($lat), $latDir, abs($lng), $lngDir);
    }

    /**
     * Generate Google Maps URL pinned directly to specific latitude and longitude coordinates.
     */
    public function getGoogleMapsUrlAttribute(): ?string
    {
        if ($this->country_code === 'LAN' || GeoIpService::isPrivateIp($this->ip_address)) {
            return null;
        }

        $coords = $this->effective_coordinates;
        if ($coords && $coords['lat'] !== null && $coords['lng'] !== null) {
            $lat = number_format($coords['lat'], 6, '.', '');
            $lng = number_format($coords['lng'], 6, '.', '');

            // Drops a pin directly on exact GPS coordinates (lat,lng)
            return "https://www.google.com/maps?q={$lat},{$lng}";
        }

        $queryParts = [];
        if ($this->city && $this->city !== 'Unknown City') {
            $queryParts[] = $this->city;
        }
        if ($this->country_name && $this->country_name !== 'Unknown' && $this->country_name !== 'Unknown Location') {
            $queryParts[] = $this->country_name;
        } elseif ($this->country_code && $this->country_code !== 'UN') {
            $queryParts[] = $this->country_code;
        }

        if (empty($queryParts)) {
            return null;
        }

        return 'https://www.google.com/maps?q=' . urlencode(implode(', ', $queryParts));
    }

    public static function logIncident(
        string $ip,
        string $threatType,
        string $severity = 'medium',
        string $actionTaken = 'blocked',
        ?string $payload = null,
        ?Request $request = null,
        ?string $tenantId = null,
        ?string $storeName = null
    ): self {
        $req = $request ?: request();
        $geo = GeoIpService::lookup($ip, $req);

        // Resolve targeted tenant if not explicitly provided
        if (! $tenantId) {
            if (function_exists('tenant') && tenant()) {
                $tenantId = (string) tenant()->getTenantKey();
            } elseif (function_exists('admin_current_tenant') && admin_current_tenant()) {
                $tenantId = (string) admin_current_tenant()->id;
            } elseif ($req && $req->hasSession() && $req->session()->get('admin_selected_tenant_id')) {
                $tenantId = (string) $req->session()->get('admin_selected_tenant_id');
            } elseif ($req && ($req->header('X-Tenant') || $req->header('X-Tenant-Id'))) {
                $tenantId = $req->header('X-Tenant') ?: $req->header('X-Tenant-Id');
            } elseif ($req && ($req->header('X-Store') || $req->header('X-Store-Name'))) {
                $storeName = $req->header('X-Store') ?: $req->header('X-Store-Name');
            } elseif ($req && ($req->input('tenant') || $req->input('tenant_id'))) {
                $tenantId = (string) ($req->input('tenant') ?: $req->input('tenant_id'));
            } elseif ($req && $req->getHost()) {
                try {
                    $host = $req->getHost();
                    $domainModel = class_exists(\Stancl\Tenancy\Database\Models\Domain::class)
                        ? \Stancl\Tenancy\Database\Models\Domain::where('domain', $host)->first()
                        : null;
                    if ($domainModel) {
                        $tenantId = (string) $domainModel->tenant_id;
                    } else {
                        // Check subdomain against tenants
                        $parts = explode('.', $host);
                        if (count($parts) > 1) {
                            $sub = $parts[0];
                            $matchedTenant = Tenant::where('id', $sub)
                                ->orWhere('alias', $sub)
                                ->first();
                            if ($matchedTenant) {
                                $tenantId = (string) $matchedTenant->id;
                            }
                        }
                    }
                } catch (\Throwable $e) {}
            }
        }

        // Resolve store name if tenant is identified
        if (! $storeName && $tenantId) {
            try {
                $t = Tenant::find($tenantId) ?? Tenant::where('alias', $tenantId)->first();
                if ($t) {
                    $storeName = $t->general_settings['store_name'] ?? $t->db_name ?? (string) $t->id;
                } else {
                    $storeName = $tenantId;
                }
            } catch (\Throwable $e) {
                $storeName = $tenantId;
            }
        }

        $incident = static::query()->create([
            'incident_id' => 'SEC-' . strtoupper(Str::random(8)),
            'tenant_id' => $tenantId,
            'store_name' => $storeName,
            'ip_address' => $ip,
            'country_code' => $geo['country_code'] ?? null,
            'country_name' => $geo['country_name'] ?? null,
            'city' => $geo['city'] ?? null,
            'latitude' => $geo['latitude'] ?? null,
            'longitude' => $geo['longitude'] ?? null,
            'is_vpn' => (bool) ($geo['is_vpn'] ?? false),
            'isp' => $geo['isp'] ?? null,
            'threat_type' => $threatType,
            'severity' => in_array($severity, ['low', 'medium', 'high', 'critical']) ? $severity : 'medium',
            'request_method' => $req ? $req->method() : 'CLI',
            'request_url' => $req ? Str::limit($req->fullUrl(), 500) : 'N/A',
            'user_agent' => $req ? Str::limit($req->userAgent(), 500) : null,
            'payload' => $payload ? Str::limit($payload, 2000) : null,
            'action_taken' => $actionTaken,
        ]);

        try {
            $incident->dispatchTelegramAlert();
        } catch (\Throwable $e) {
            // Never let notification failures interrupt security enforcement
        }

        return $incident;
    }

    /**
     * Format this incident as an executive tree-branch Telegram alert message matching the screenshot format.
     */
    public function formatTelegramMessage(?Tenant $targetTenant = null): string
    {
        /** @var \App\Services\TelegramNotificationService $service */
        $service = app(\App\Services\TelegramNotificationService::class);
        return $service->formatSecurityAlertMessage($this, $targetTenant);
    }

    /**
     * Dispatch an immediate real-time Telegram threat alert.
     */
    public function dispatchTelegramAlert(?string $overrideBotToken = null, ?string $overrideChatId = null): bool
    {
        // 1. Check if Telegram alerts are enabled for security incidents
        if (! SecuritySetting::getBool('telegram_security_alerts_enabled', true)) {
            return false;
        }

        // 2. Filter by minimum severity
        $minSeverity = SecuritySetting::get('telegram_security_min_severity', 'medium');
        $severityRank = ['low' => 1, 'medium' => 2, 'high' => 3, 'critical' => 4];
        $currentRank = $severityRank[strtolower($this->severity ?: 'medium')] ?? 2;
        $requiredRank = $severityRank[strtolower($minSeverity)] ?? 2;

        if ($minSeverity !== 'all' && $currentRank < $requiredRank) {
            return false;
        }

        // 3. Resolve Telegram Service & Bot credentials
        /** @var \App\Services\TelegramNotificationService $service */
        $service = app(\App\Services\TelegramNotificationService::class);

        $botToken = $overrideBotToken ?: SecuritySetting::get('telegram_security_bot_token');
        $chatId = $overrideChatId ?: SecuritySetting::get('telegram_security_chat_id');

        $targetTenant = $this->tenant_id ? Tenant::find($this->tenant_id) : null;
        if (empty($botToken) || empty($chatId)) {
            $botToken = $botToken ?: $service->getBotToken($targetTenant);
            $chatId = $chatId ?: $service->getChatId($targetTenant);
        }

        if (empty($botToken) || empty($chatId)) {
            return false;
        }

        // 4. Build Telegram HTML message using the standardized tree-branch format matching the Error Alert style
        $html = $service->formatSecurityAlertMessage($this, $targetTenant);

        // 5. Build Buttons
        $buttons = [];
        $googleMapsUrl = $this->google_maps_url;
        if ($googleMapsUrl && $service->isValidTelegramUrl($googleMapsUrl)) {
            $buttons[] = [
                'text' => '🗺️ Google Maps Location',
                'url' => $googleMapsUrl,
            ];
        }

        try {
            $dashboardUrl = route('admin.security.index', ['tab' => 'monitor']);
            if ($service->isValidTelegramUrl($dashboardUrl)) {
                $buttons[] = [
                    'text' => '🛡️ Open Security Center',
                    'url' => $dashboardUrl,
                ];
            }
        } catch (\Throwable $e) {}

        $replyMarkup = ! empty($buttons) ? ['inline_keyboard' => array_chunk($buttons, 2)] : null;

        return $service->sendDirectMessage($html, $botToken, $chatId, $replyMarkup);
    }
}
