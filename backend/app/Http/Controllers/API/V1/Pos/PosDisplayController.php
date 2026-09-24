<?php

namespace App\Http\Controllers\API\V1\Pos;

use App\Events\PosDisplaySyncEvent;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PosDisplayController extends Controller
{
    /**
     * Cache repository that operates safely across tenant switches without tagging exceptions.
     */
    protected function cacheStore()
    {
        return Cache::store((string) config('cache.pos_display_store', 'pos_display'));
    }

    /**
     * Broadcast live cart and checkout status to customer-facing display.
     */
    public function sync(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:128'],
            'action' => ['required', 'string', 'in:cart_updated,payment_pending,payment_success,cart_cleared,idle'],
            'cart' => ['nullable', 'array'],
            'totals' => ['nullable', 'array'],
            'payment' => ['nullable', 'array'],
            'customer' => ['nullable', 'array'],
            'branch' => ['nullable', 'array'],
            'company' => ['nullable', 'array'],
        ]);

        $token = $validated['token'];

        $payload = [
            'action' => $validated['action'],
            'cart' => $validated['cart'] ?? [],
            'totals' => $validated['totals'] ?? null,
            'payment' => $validated['payment'] ?? null,
            'customer' => $validated['customer'] ?? null,
            'branch' => $validated['branch'] ?? null,
            'company' => $validated['company'] ?? null,
            'updated_at' => now()->toIso8601String(),
        ];

        // Cache latest display state so screens refreshing or connecting mid-transaction recover state
        $this->cacheStore()->put("pos_display_state_{$token}", $payload, now()->addHours(6));

        $this->ensureTenancy($request);
        if (tenant()) {
            $this->cacheStore()->put("pos_display_tenant_{$token}", tenant('id'), now()->addHours(24));
        }

        // Instantly broadcast to Soketi WebSocket
        broadcast(new PosDisplaySyncEvent($token, $payload));

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $token,
                'action' => $validated['action'],
                'timestamp' => $payload['updated_at'],
            ],
        ]);
    }

    /**
     * Retrieve the active display state for a token upon connection/page load.
     */
    public function state(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:128'],
        ]);

        $token = $validated['token'];
        $state = $this->cacheStore()->get("pos_display_state_{$token}");

        if (!$state) {
            $state = [
                'action' => 'idle',
                'cart' => [],
                'totals' => null,
                'payment' => null,
                'customer' => null,
                'branch' => null,
                'company' => null,
                'updated_at' => now()->toIso8601String(),
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $state,
        ]);
    }

    /**
     * Resolve and initialize active tenant context if not already set.
     */
    protected function ensureTenancy(Request $request): ?\App\Models\Tenant
    {
        if (tenant()) {
            return tenant();
        }

        $tenantId = $request->header('X-Tenant-Id')
            ?: $request->header('X-Tenant')
            ?: $request->get('tenant_id')
            ?: $request->get('tenant');

        if ($tenantId && class_exists(\App\Models\Tenant::class)) {
            $tenant = \App\Models\Tenant::where('id', $tenantId)->orWhere('alias', $tenantId)->first();
            if ($tenant) {
                tenancy()->initialize($tenant);
                return $tenant;
            }
        }

        $token = (string) $request->get('token', $request->input('token', ''));
        if ($token) {
            $cachedTenantId = $this->cacheStore()->get("pos_display_tenant_{$token}");
            if ($cachedTenantId && class_exists(\App\Models\Tenant::class)) {
                $tenant = \App\Models\Tenant::find($cachedTenantId);
                if ($tenant) {
                    tenancy()->initialize($tenant);
                    return $tenant;
                }
            }
        }

        if (class_exists(\App\Models\Tenant::class)) {
            $tenant = \App\Models\Tenant::where('status', 'Active')->orderBy('created_at')->first();
            if ($tenant) {
                tenancy()->initialize($tenant);
                return $tenant;
            }
        }

        return null;
    }

    /**
     * Retrieve promotional slides split by type (second_screen, mobile, web) strictly from database.
     */
    public function promotions(Request $request): JsonResponse
    {
        $this->ensureTenancy($request);

        $type = strtolower((string) $request->get('type', $request->get('placement', 'second_screen')));
        $token = (string) $request->get('token', '');

        // Map placements
        $placements = match ($type) {
            'web', 'website' => ['Website', 'web', 'All', 'all'],
            'mobile' => ['Mobile', 'mobile', 'All', 'all'],
            'second_screen', 'secondscreen', 'display', 'pos' => ['second_screen', 'SecondScreen', 'Display', 'POS', 'All', 'all'],
            'all' => ['second_screen', 'SecondScreen', 'Display', 'POS', 'Mobile', 'mobile', 'Website', 'web', 'All', 'all'],
            default => [$type, 'All', 'all'],
        };

        // 1. Station-specific cached custom slides
        if ($token) {
            $tokenSlides = $this->cacheStore()->get("pos_display_promotions_{$token}");
            if (is_array($tokenSlides) && !empty($tokenSlides)) {
                return response()->json([
                    'success' => true,
                    'type' => $type,
                    'data' => $tokenSlides,
                ]);
            }
        }

        // 2. Database sliders from tenant table
        $dbSlides = [];
        try {
            if (class_exists(\App\Models\Slider::class) && tenant()) {
                $sliders = \App\Models\Slider::query()
                    ->with('image')
                    ->where('status', 'Active')
                    ->whereIn('placement', $placements)
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get();

                foreach ($sliders as $slider) {
                    $dbSlides[] = $slider->toPromoSlidePayload();
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed loading sliders from DB: ' . $e->getMessage());
        }

        if (!empty($dbSlides)) {
            return response()->json([
                'success' => true,
                'type' => $type,
                'data' => $dbSlides,
            ]);
        }

        // 3. Global cached slides for this type if previously configured
        $cachedDefault = $this->cacheStore()->get("pos_display_promotions_{$type}");
        if (is_array($cachedDefault) && !empty($cachedDefault)) {
            return response()->json([
                'success' => true,
                'type' => $type,
                'data' => $cachedDefault,
            ]);
        }

        // 4. Return clean empty data (never hardcoded mock data)
        return response()->json([
            'success' => true,
            'type' => $type,
            'data' => [],
        ]);
    }

    /**
     * Save/update promotional slides split by type directly to tenant database.
     */
    public function savePromotions(Request $request): JsonResponse
    {
        $this->ensureTenancy($request);

        $validated = $request->validate([
            'type' => ['nullable', 'string', 'max:32'],
            'placement' => ['nullable', 'string', 'max:32'],
            'token' => ['nullable', 'string', 'max:128'],
            'slides' => ['required', 'array', 'min:1'],
            'slides.*.id' => ['required'],
            'slides.*.type' => ['required', 'string', 'in:video,image,gradient'],
            'slides.*.badge' => ['required', 'string', 'max:64'],
            'slides.*.title' => ['required', 'string', 'max:128'],
            'slides.*.subtitle' => ['nullable', 'string', 'max:255'],
            'slides.*.discount' => ['nullable', 'string', 'max:64'],
            'slides.*.mediaUrl' => ['nullable', 'string', 'max:500'],
            'slides.*.gradient' => ['nullable', 'string', 'max:255'],
            'slides.*.icon' => ['nullable', 'string', 'max:64'],
            'slides.*.tag' => ['nullable', 'string', 'max:128'],
            'slides.*.badgeBg' => ['nullable', 'string', 'max:64'],
            'slides.*.badgeColor' => ['nullable', 'string', 'max:64'],
        ]);

        $type = strtolower((string) ($validated['type'] ?? ($validated['placement'] ?? 'second_screen')));
        $token = (string) ($validated['token'] ?? '');
        $slides = $validated['slides'];

        // Normalize slides
        $normalized = array_map(function ($s, $idx) use ($type) {
            return [
                'id' => (string) ($s['id'] ?? 'slide-' . ($idx + 1)),
                'type' => (string) ($s['type'] ?? 'gradient'),
                'badge' => (string) ($s['badge'] ?? 'PROMOTION'),
                'badgeBg' => (string) ($s['badgeBg'] ?? 'rgba(255, 255, 255, 0.95)'),
                'badgeColor' => (string) ($s['badgeColor'] ?? '#0f172a'),
                'title' => (string) ($s['title'] ?? 'Store Special'),
                'subtitle' => (string) ($s['subtitle'] ?? ''),
                'discount' => (string) ($s['discount'] ?? ''),
                'mediaUrl' => (string) ($s['mediaUrl'] ?? ''),
                'gradient' => (string) ($s['gradient'] ?? 'linear-gradient(135deg, #1e1b4b 0%, #312e81 45%, #4338ca 100%)'),
                'icon' => (string) ($s['icon'] ?? 'ri-gift-line'),
                'tag' => (string) ($s['tag'] ?? 'Available at counter'),
                'placement' => $type,
                'sort_order' => $idx + 1,
                'status' => 'Active',
            ];
        }, $slides, array_keys($slides));

        // Save into cache for immediate token retrieval
        if ($token) {
            $this->cacheStore()->put("pos_display_promotions_{$token}", $normalized, now()->addDays(30));
            if (tenant()) {
                $this->cacheStore()->put("pos_display_tenant_{$token}", tenant('id'), now()->addDays(30));
            }
        }
        $this->cacheStore()->put("pos_display_promotions_{$type}", $normalized, now()->addDays(30));

        // Persist directly into tenant Slider database table
        try {
            if (class_exists(\App\Models\Slider::class) && tenant()) {
                foreach ($normalized as $index => $slide) {
                    $sliderId = is_numeric($slide['id']) ? (int) $slide['id'] : null;
                    $attributes = [
                        'title' => $slide['title'],
                        'subtitle' => $slide['subtitle'] ?: null,
                        'placement' => $type,
                        'media_type' => $slide['type'],
                        'media_url' => $slide['mediaUrl'] ?: null,
                        'badge' => $slide['badge'],
                        'badge_bg' => $slide['badgeBg'] ?: null,
                        'badge_color' => $slide['badgeColor'] ?: null,
                        'discount' => $slide['discount'] ?: null,
                        'gradient' => $slide['gradient'] ?: null,
                        'icon' => $slide['icon'] ?: null,
                        'tag' => $slide['tag'] ?: null,
                        'sort_order' => $index + 1,
                        'status' => 'Active',
                    ];

                    if ($sliderId) {
                        \App\Models\Slider::updateOrCreate(['id' => $sliderId], $attributes);
                    } else {
                        \App\Models\Slider::updateOrCreate(
                            [
                                'placement' => $type,
                                'title' => $slide['title'],
                            ],
                            $attributes
                        );
                    }
                }
                \App\Models\Slider::flushQueryCache();
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed persisting promotions to DB: ' . $e->getMessage());
        }

        // Broadcast live Soketi WebSocket sync so connected customer screens update immediately!
        if ($token) {
            broadcast(new PosDisplaySyncEvent($token, [
                'action' => 'promotions_updated',
                'promotions' => $normalized,
                'type' => $type,
            ]));
        }

        return response()->json([
            'success' => true,
            'message' => 'Promotions saved and broadcast successfully.',
            'type' => $type,
            'data' => $normalized,
        ]);
    }
}
