<?php

namespace App\Livewire\Admin;

use App\Models\MonitoringPreference;
use App\Monitoring\Services\MonitoringService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class MonitoringDashboard extends Component
{
    #[Locked]
    public bool $liveEnabled = false;

    #[Locked]
    public int $refreshSeconds = 15;

    protected ?array $dashboard = null;

    public function boot(): void
    {
        abort_unless(auth()->check() && ! admin_is_tenant_user() && ! tenant(), 403);
    }

    public function mount(): void
    {
        $this->liveEnabled = $this->savedLivePreference();
        $this->refreshSeconds = max(5, min(300, (int) config('monitoring.refresh_seconds', 15)));
    }

    public function refreshDashboard(bool $force = false): array
    {
        $this->liveEnabled = $this->savedLivePreference();

        if (! $force && ! $this->liveEnabled) {
            $this->skipRender();

            return ['liveEnabled' => false, 'refreshed' => false];
        }

        $this->dashboard = app(MonitoringService::class)->dashboard();

        return [
            'liveEnabled' => $this->liveEnabled,
            'refreshed' => true,
            'updatedAt' => now()->format('H:i:s'),
        ];
    }

    public function setLiveEnabled(bool $enabled): void
    {
        MonitoringPreference::query()->updateOrCreate(
            ['user_id' => (int) auth()->id()],
            ['live_enabled' => $enabled]
        );

        $this->liveEnabled = $enabled;
        $this->skipRender();
    }

    public function render(): View
    {
        // Keep metric history out of the client-writable Livewire snapshot.
        $this->dashboard ??= app(MonitoringService::class)->dashboard();

        return view('livewire.admin.monitoring-dashboard', $this->dashboard);
    }

    private function savedLivePreference(): bool
    {
        try {
            return (bool) MonitoringPreference::query()->where('user_id', auth()->id())->value('live_enabled');
        } catch (\Throwable $exception) {
            report($exception);

            return false;
        }
    }
}
