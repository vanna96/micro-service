<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\FacebookLoginService;
use Illuminate\Http\Request;

class PublicStoreController extends Controller
{
    public static function summary(Tenant $tenant): array
    {
        $settings = is_array($tenant->general_settings) ? $tenant->general_settings : [];

        return [
            'id' => (string) $tenant->id,
            'name' => (string) ($settings['store_name'] ?? $tenant->alias ?? $tenant->id),
            'logo_url' => (string) ($settings['logo_url'] ?? ''),
            'domain' => (string) ($tenant->domains->sortBy('domain')->first()?->domain ?? ''),
            'domains' => $tenant->domains->sortBy('domain')->pluck('domain')->values()->all(),
            'facebook_login_enabled' => app(FacebookLoginService::class)->isConfigured($tenant),
            'google_login_enabled' => app(\App\Services\GoogleLoginService::class)->isConfigured($tenant),
        ];
    }

    public function index(Request $request)
    {
        $stores = Tenant::query()->where('status', 'Active')->whereHas('domains')
            ->with('domains')->orderBy('alias')->get()
            ->filter(fn (Tenant $tenant) => $tenant->status === 'Active')
            ->map(fn (Tenant $tenant) => self::summary($tenant));
        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $stores = $stores->filter(fn (array $store) => mb_stripos($store['name'], $search) !== false);
        }

        return response()->json(['success' => true, 'data' => $stores->values()]);
    }
}
