<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SecuritySetting extends Model
{
    use HasFactory;

    protected $table = 'security_settings';

    protected $fillable = [
        'key',
        'value',
    ];

    /**
     * In-memory cache for the request lifecycle.
     */
    protected static ?array $memorySettings = null;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->setConnection(config('tenancy.database.central_connection') ?: config('database.default', 'central'));
    }

    public static function cacheKey(): string
    {
        return 'security_settings_all';
    }

    public static function allSettings(): array
    {
        if (static::$memorySettings !== null) {
            return static::$memorySettings;
        }

        try {
            // Use file store directly to bypass tenant tag decorator
            $settings = Cache::store('file')->remember(self::cacheKey(), 120, function () {
                return static::query()->pluck('value', 'key')->toArray();
            });

            static::$memorySettings = is_array($settings) ? $settings : [];
            return static::$memorySettings;
        } catch (\Throwable $e) {
            try {
                $settings = static::query()->pluck('value', 'key')->toArray();
                static::$memorySettings = $settings;
                return $settings;
            } catch (\Throwable $ex) {
                return [];
            }
        }
    }

    public static function get(string $key, $default = null)
    {
        $settings = self::allSettings();
        return array_key_exists($key, $settings) ? $settings[$key] : $default;
    }

    public static function getBool(string $key, bool $default = false): bool
    {
        $val = self::get($key, $default ? '1' : '0');
        return filter_var($val, FILTER_VALIDATE_BOOLEAN);
    }

    public static function getInt(string $key, int $default = 0): int
    {
        $val = self::get($key, (string) $default);
        return is_numeric($val) ? (int) $val : $default;
    }

    public static function set(string $key, $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => is_array($value) ? json_encode($value) : (string) $value]
        );

        static::$memorySettings = null;
        try {
            Cache::store('file')->forget(self::cacheKey());
        } catch (\Throwable $e) {
            // Ignore cache store failure
        }
    }

    public static function setMany(array $keyValues): void
    {
        foreach ($keyValues as $key => $value) {
            static::query()->updateOrCreate(
                ['key' => $key],
                ['value' => is_array($value) ? json_encode($value) : (string) $value]
            );
        }

        static::$memorySettings = null;
        try {
            Cache::store('file')->forget(self::cacheKey());
        } catch (\Throwable $e) {
            // Ignore cache store failure
        }
    }

    public static function clearMemoryCache(): void
    {
        static::$memorySettings = null;
    }
}
