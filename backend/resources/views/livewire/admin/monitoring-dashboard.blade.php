<div id="monitoring-component" data-refresh-seconds="{{ $refreshSeconds }}" data-live-enabled="{{ $liveEnabled ? 'true' : 'false' }}">
    <div wire:ignore class="monitor-live-bar d-flex flex-wrap align-items-center justify-content-between gap-2 px-3 py-2 mb-3" role="status" aria-live="polite">
        <div class="d-flex align-items-center gap-2">
            <span id="monitor-live-dot" class="monitor-live-dot{{ $liveEnabled ? '' : ' is-paused' }}" aria-hidden="true"></span>
            <strong id="monitor-live-state">{{ $liveEnabled ? __('Live') : __('Paused') }}</strong>
            <span class="small text-muted">
                {{ __('Updates every :seconds seconds only while Live', ['seconds' => $refreshSeconds]) }}
                · {{ __('Last update:') }} <span id="monitor-live-updated">{{ now()->format('H:i:s') }}</span>
            </span>
        </div>
        <div class="d-flex gap-2">
            <button id="monitor-refresh-now" type="button" class="btn btn-sm btn-outline-primary">
                <i class="uil uil-refresh me-1"></i>{{ __('Refresh now') }}
            </button>
            <button id="monitor-toggle-live" type="button" class="btn btn-sm btn-outline-secondary" aria-pressed="{{ $liveEnabled ? 'false' : 'true' }}">
                <i class="uil {{ $liveEnabled ? 'uil-pause' : 'uil-play' }} me-1"></i><span>{{ $liveEnabled ? __('Pause') : __('Resume') }}</span>
            </button>
        </div>
    </div>

    <div id="monitoring-dashboard-content">
        @include('admin.monitoring._content')
    </div>
</div>
