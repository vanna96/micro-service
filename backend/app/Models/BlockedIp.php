<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class BlockedIp extends Model
{
    use HasFactory;

    protected $table = 'blocked_ips';

    protected $fillable = [
        'ip_address',
        'reason',
        'threat_type',
        'blocked_by',
        'strike_count',
        'expires_at',
    ];

    protected $casts = [
        'strike_count' => 'integer',
        'expires_at' => 'datetime',
    ];

    /**
     * In-memory cache for the current request.
     */
    protected static array $memoryBlocked = [];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->setConnection(config('tenancy.database.central_connection') ?: config('database.default', 'central'));
    }

    public static function cacheKey(string $ip): string
    {
        return 'security_blocked_ip_' . md5($ip);
    }

    public static function isBlocked(string $ip): bool
    {
        if ($ip === '127.0.0.1' || $ip === '::1') {
            return false;
        }

        if (array_key_exists($ip, static::$memoryBlocked)) {
            return static::$memoryBlocked[$ip];
        }

        $cacheKey = self::cacheKey($ip);

        try {
            // Use file store directly to bypass tenant tag decorator
            $isBlocked = Cache::store('file')->remember($cacheKey, 60, function () use ($ip) {
                return static::checkDatabase($ip);
            });

            static::$memoryBlocked[$ip] = (bool) $isBlocked;
            return static::$memoryBlocked[$ip];
        } catch (\Throwable $e) {
            $isBlocked = static::checkDatabase($ip);
            static::$memoryBlocked[$ip] = $isBlocked;
            return $isBlocked;
        }
    }

    protected static function checkDatabase(string $ip): bool
    {
        $record = static::query()->where('ip_address', $ip)->first();

        if (! $record) {
            return false;
        }

        if ($record->expires_at && $record->expires_at->isPast()) {
            $record->delete();
            return false;
        }

        return true;
    }

    public static function block(
        string $ip,
        ?string $reason = null,
        string $threatType = 'manual',
        ?int $durationHours = null,
        ?string $blockedBy = 'system'
    ): self {
        $record = static::query()->where('ip_address', $ip)->first();

        $expiresAt = $durationHours && $durationHours > 0
            ? Carbon::now()->addHours($durationHours)
            : null;

        if ($record) {
            $record->increment('strike_count');
            $record->update([
                'reason' => $reason ?: $record->reason,
                'threat_type' => $threatType ?: $record->threat_type,
                'blocked_by' => $blockedBy ?: $record->blocked_by,
                'expires_at' => $expiresAt ?: $record->expires_at,
            ]);
        } else {
            $record = static::query()->create([
                'ip_address' => $ip,
                'reason' => $reason ?: 'Suspicious security threat detected',
                'threat_type' => $threatType,
                'blocked_by' => $blockedBy,
                'strike_count' => 1,
                'expires_at' => $expiresAt,
            ]);
        }

        static::$memoryBlocked[$ip] = true;

        try {
            Cache::store('file')->put(self::cacheKey($ip), true, 60);
        } catch (\Throwable $e) {
            // Ignore cache failure
        }

        return $record;
    }

    public static function unblock(string $ip): bool
    {
        unset(static::$memoryBlocked[$ip]);

        try {
            Cache::store('file')->forget(self::cacheKey($ip));
        } catch (\Throwable $e) {
            // Ignore cache failure
        }

        return (bool) static::query()->where('ip_address', $ip)->delete();
    }

    public static function clearMemoryCache(): void
    {
        static::$memoryBlocked = [];
    }
}
