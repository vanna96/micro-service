<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MonitoringPreference;
use App\Monitoring\Services\MonitoringService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MonitoringController extends Controller
{
    public function index(): View
    {
        return view('admin.monitoring.index');
    }

    public function live(MonitoringService $monitoring, Request $request): View|JsonResponse
    {
        if (! $request->boolean('once')
            && ! $this->liveEnabled((int) $request->user()->getAuthIdentifier())) {
            return response()->json([
                'live_enabled' => false,
            ], 409);
        }

        return view('admin.monitoring._content', $monitoring->dashboard());
    }

    public function updateLivePreference(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        try {
            $preference = MonitoringPreference::query()->updateOrCreate(
                ['user_id' => (int) $request->user()->getAuthIdentifier()],
                ['live_enabled' => (bool) $validated['enabled']]
            );

            return response()->json([
                'live_enabled' => $preference->live_enabled,
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => __('The live monitoring preference could not be saved.'),
            ], 503);
        }
    }

    private function liveEnabled(int $userId): bool
    {
        try {
            return (bool) MonitoringPreference::query()
                ->where('user_id', $userId)
                ->value('live_enabled');
        } catch (\Throwable $exception) {
            report($exception);

            return false;
        }
    }
}
