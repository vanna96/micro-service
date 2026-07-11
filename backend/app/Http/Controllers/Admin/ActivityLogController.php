<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $selectedTenant = $this->requiredTenant();
        $logName = trim((string) $request->get('log_name', ''));
        $event = trim((string) $request->get('event', ''));

        $activitiesQuery = ActivityLog::query()
            ->when($logName !== '', fn ($query) => $query->where('log_name', $logName))
            ->when($event !== '', fn ($query) => $query->where('event', $event))
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        return view('admin.activity-logs.index', [
            'activities' => $activitiesQuery->paginate(25)->withQueryString(),
            'selectedTenant' => $selectedTenant,
            'selectedLogName' => $logName,
            'selectedEvent' => $event,
            'logNames' => ActivityLog::query()
                ->whereNotNull('log_name')
                ->distinct()
                ->orderBy('log_name')
                ->pluck('log_name'),
            'events' => ActivityLog::query()
                ->whereNotNull('event')
                ->distinct()
                ->orderBy('event')
                ->pluck('event'),
        ]);
    }

    public function __construct()
    {
        $this->middleware('admin.permission:activity_logs.view');
    }

    private function requiredTenant(): Tenant
    {
        $tenant = admin_current_tenant();

        abort_if(! $tenant, 404);

        return $tenant;
    }
}
