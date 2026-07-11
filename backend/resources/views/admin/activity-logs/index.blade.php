@extends('layouts.app')

@section('title', 'Activity Logs')
@section('page_title', 'Activity Logs')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <div>
                        <h4 class="card-title mb-1">Tenant Activity Logs</h4>
                        <p class="card-title-desc mb-0">
                            Review who changed tenant data and when it happened.
                        </p>
                    </div>
                </div>

                <div class="alert alert-border-left alert-light mb-4" role="alert">
                    <i class="mdi mdi-history me-2"></i>Showing activity for tenant <strong>{{ admin_tenant_display_name($selectedTenant) }}</strong>.
                </div>

                <form method="GET" class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label class="form-label">Log Name</label>
                        <select name="log_name" class="form-select">
                            <option value="">All logs</option>
                            @foreach ($logNames as $logName)
                                <option value="{{ $logName }}" @selected($selectedLogName === $logName)>{{ $logName }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Event</label>
                        <select name="event" class="form-select">
                            <option value="">All events</option>
                            @foreach ($events as $event)
                                <option value="{{ $event }}" @selected($selectedEvent === $event)>{{ ucfirst($event) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="{{ route('admin.activity-logs.index') }}" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-0">
                        <thead>
                            <tr>
                                <th>When</th>
                                <th>Actor</th>
                                <th>Action</th>
                                <th>Subject</th>
                                <th>Changes</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($activities as $activity)
                                @php
                                    $changes = $activity->changes;
                                    $attributes = collect($changes->get('attributes', []));
                                    $old = collect($changes->get('old', []));
                                    $requestDetails = (array) $activity->getExtraProperty('request', []);
                                @endphp
                                <tr>
                                    <td class="text-nowrap">
                                        <div>{{ optional($activity->created_at)->format('d M Y, h:i A') ?: '-' }}</div>
                                        <div class="text-muted font-size-12">{{ $requestDetails['method'] ?? '-' }}</div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold">{{ $activity->actor_label }}</div>
                                        <div class="text-muted font-size-12">{{ $activity->actor_scope_label }}</div>
                                    </td>
                                    <td>
                                        <span class="badge bg-soft-primary text-primary text-uppercase">{{ $activity->event ?: $activity->description }}</span>
                                        <div class="text-muted font-size-12 mt-1">{{ $activity->log_name ?: 'tenant' }}</div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold">{{ $activity->subject_label }}</div>
                                        <div class="text-muted font-size-12">{{ class_basename((string) $activity->subject_type) }}</div>
                                    </td>
                                    <td>
                                        @if ($attributes->isEmpty() && $old->isEmpty())
                                            <span class="text-muted">No field snapshot</span>
                                        @else
                                            <div class="text-muted font-size-12">
                                                {{ $attributes->keys()->implode(', ') ?: $old->keys()->implode(', ') }}
                                            </div>
                                        @endif
                                        @if (! empty($requestDetails['route']))
                                            <div class="text-muted font-size-12 mt-1">{{ $requestDetails['route'] }}</div>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">No activity logs found for this tenant.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $activities->links('pagination::bootstrap-4') }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
