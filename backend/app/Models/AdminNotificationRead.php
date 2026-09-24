<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminNotificationRead extends Model
{
    use HasFactory;

    protected $table = 'admin_notification_reads';

    protected $fillable = [
        'user_id',
        'notification_key',
        'read_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'read_at' => 'datetime',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->setConnection(config('tenancy.database.central_connection') ?: config('database.default', 'central'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Mark a single notification key as read for a user (concurrency-safe)
     */
    public static function markKeyAsRead(int $userId, string $key): self
    {
        $now = now();

        try {
            static::upsert(
                [
                    [
                        'user_id' => $userId,
                        'notification_key' => $key,
                        'read_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                ],
                ['user_id', 'notification_key'],
                ['read_at', 'updated_at']
            );
        } catch (\Throwable $e) {
            // Fallback for driver quirks or concurrent updates
            try {
                return static::updateOrCreate(
                    [
                        'user_id' => $userId,
                        'notification_key' => $key,
                    ],
                    [
                        'read_at' => $now,
                    ]
                );
            } catch (\Illuminate\Database\QueryException $qe) {
                // 1062 Duplicate entry (SQLSTATE 23000) under concurrent requests
                if ($qe->errorInfo[1] === 1062 || $qe->getCode() === 23000 || str_contains($qe->getMessage(), '1062')) {
                    return static::where('user_id', $userId)
                        ->where('notification_key', $key)
                        ->first() ?? new static([
                            'user_id' => $userId,
                            'notification_key' => $key,
                            'read_at' => $now,
                        ]);
                }
                throw $qe;
            }
        }

        return static::where('user_id', $userId)
            ->where('notification_key', $key)
            ->first() ?? new static([
                'user_id' => $userId,
                'notification_key' => $key,
                'read_at' => $now,
            ]);
    }

    /**
     * Mark multiple notification keys as read for a user (concurrency-safe batch upsert)
     *
     * @param int $userId
     * @param array<string> $keys
     */
    public static function markKeysAsRead(int $userId, array $keys): int
    {
        $keys = array_values(array_unique(array_filter($keys, fn ($k) => trim((string) $k) !== '')));
        if (empty($keys)) {
            return 0;
        }

        $now = now();
        $records = array_map(fn ($k) => [
            'user_id' => $userId,
            'notification_key' => $k,
            'read_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ], $keys);

        try {
            static::upsert(
                $records,
                ['user_id', 'notification_key'],
                ['read_at', 'updated_at']
            );
        } catch (\Throwable $e) {
            foreach ($keys as $key) {
                try {
                    static::updateOrCreate(
                        ['user_id' => $userId, 'notification_key' => $key],
                        ['read_at' => $now]
                    );
                } catch (\Throwable $qe) {
                    // Ignore duplicate key race conditions
                }
            }
        }

        return count($keys);
    }

    /**
     * Mark all notifications (or all of a specific type/tab) as read for a user
     */
    public static function markAllAsRead(int $userId, ?string $type = null): void
    {
        $normalizedType = strtolower(trim((string) $type));

        $keys = match ($normalizedType) {
            'threat', 'threats' => ['__all_threats__'],
            'order', 'orders' => ['__all_orders__'],
            default => ['__all__', '__all_threats__', '__all_orders__'],
        };

        static::markKeysAsRead($userId, $keys);
    }

    /**
     * Get array of read keys for a user keyed by notification_key for O(1) lookup
     *
     * @param int $userId
     * @return array<string, bool>
     */
    public static function getReadKeysMap(int $userId): array
    {
        return static::where('user_id', $userId)
            ->pluck('notification_key')
            ->flip()
            ->all();
    }

    /**
     * Get structured user read state including global/tab timestamps and specific keys
     *
     * @param int $userId
     * @return array{all_read_at: ?\Carbon\Carbon, threats_read_at: ?\Carbon\Carbon, orders_read_at: ?\Carbon\Carbon, keys_map: array<string, bool>}
     */
    public static function getUserReadState(int $userId): array
    {
        $records = static::where('user_id', $userId)->get(['notification_key', 'read_at']);
        $allReadAt = null;
        $threatsReadAt = null;
        $ordersReadAt = null;
        $keysMap = [];

        foreach ($records as $r) {
            if ($r->notification_key === '__all__') {
                $allReadAt = $r->read_at;
            } elseif ($r->notification_key === '__all_threats__') {
                $threatsReadAt = $r->read_at;
            } elseif ($r->notification_key === '__all_orders__') {
                $ordersReadAt = $r->read_at;
            } else {
                $keysMap[$r->notification_key] = true;
            }
        }

        return [
            'all_read_at' => $allReadAt,
            'threats_read_at' => $threatsReadAt,
            'orders_read_at' => $ordersReadAt,
            'keys_map' => $keysMap,
        ];
    }

    /**
     * Determine if a notification item is read based on keys map or category/global read timestamp
     */
    public static function isNotificationRead(array $readState, string $key, ?int $timestamp = null, string $type = 'general'): bool
    {
        if (isset($readState['keys_map'][$key])) {
            return true;
        }

        if ($timestamp) {
            $itemTime = \Carbon\Carbon::createFromTimestamp($timestamp);

            if ($readState['all_read_at'] && $itemTime->lte($readState['all_read_at'])) {
                return true;
            }

            if (($type === 'threat' || $type === 'threats') && $readState['threats_read_at'] && $itemTime->lte($readState['threats_read_at'])) {
                return true;
            }

            if (($type === 'order' || $type === 'orders') && $readState['orders_read_at'] && $itemTime->lte($readState['orders_read_at'])) {
                return true;
            }
        }

        return false;
    }
}
