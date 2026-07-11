@extends('layouts.app')

@section('title', 'Dashboard')
@section('page_title', 'Dashboard')

@push('styles')
<style>
    .tenant-summary-card {
        border: 1px dashed #d6deee;
        background: linear-gradient(135deg, #f8fbff 0%, #eef4ff 100%);
    }
</style>
@endpush

@section('content')
<div class="row">
    <div class="col-md-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <p class="text-muted mb-0">{{ __('Theme') }}</p>
                <h4 class="mb-1 mt-1">{{ __('Minible') }}</h4>
                <p class="text-muted mt-3 mb-0">{{ __('Using the horizontal layout template you selected.') }}</p>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <p class="text-muted mb-0">{{ __('Authenticated User') }}</p>
                <h4 class="mb-1 mt-1">{{ auth()->user()->name }}</h4>
                <p class="text-muted mt-3 mb-0">{{ __('Signed in via username, email, or phone.') }}</p>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <p class="text-muted mb-0">{{ __('Active Tenant') }}</p>
                <h4 class="mb-1 mt-1">{{ $selectedTenant?->id ?: __('Not selected') }}</h4>
                <p class="text-muted mt-3 mb-0">
                    @if ($selectedTenant)
                        {{ admin_tenant_display_name($selectedTenant) ?: 'Tenant ready for admin work.' }}
                    @elseif ($tenantOptions->isEmpty())
                        {{ __('No active tenant is assigned to your account.') }}
                    @else
                        {{ __('Choose a tenant to unlock the admin area.') }}
                    @endif
                </p>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <p class="text-muted mb-0">{{ __('Tenant Access') }}</p>
                <h4 class="mb-1 mt-1">{{ $tenantOptions->count() }}</h4>
                @if (admin_can_switch_tenant() && $tenantOptions->isNotEmpty())
                    <button type="button" class="btn btn-info btn-sm waves-effect waves-light mt-3" data-bs-toggle="modal" data-bs-target="#tenantSelectionModal">
                        {{ $selectedTenant ? __('Switch Tenant') : __('Select Tenant') }}
                    </button>
                @elseif ($selectedTenant)
                    <span class="badge bg-soft-primary text-primary mt-3">{{ __('Tenant Locked') }}</span>
                @else
                    <span class="badge bg-soft-danger text-danger mt-3">{{ __('No Tenant Available') }}</span>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="row mt-3">
    <div class="col-12">
        <div class="card tenant-summary-card">
            <div class="card-body d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                <div>
                    <h4 class="mb-1">
                        @if ($selectedTenant)
                            {{ __('Tenant session is active') }}
                        @elseif ($tenantOptions->isEmpty())
                            {{ __('No tenant assigned') }}
                        @else
                            {{ __('Tenant selection required') }}
                        @endif
                    </h4>
                    <p class="text-muted mb-0">
                        @if ($selectedTenant)
                            {{ __('You are working under tenant') }} <strong>{{ admin_tenant_display_name($selectedTenant) }}</strong>.
                        @elseif ($tenantOptions->isEmpty())
                            {{ __('This account does not have any active tenant assigned yet. Please contact your administrator or sign out.') }}
                        @else
                            {{ __('Pick one of your assigned tenants first. Until then, only the Administrator and Tenants menus stay available.') }}
                        @endif
                    </p>
                </div>
                <div class="d-flex gap-2">
                    @if (admin_can_switch_tenant() && $tenantOptions->isNotEmpty())
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#tenantSelectionModal">
                            {{ $selectedTenant ? __('Change Tenant') : __('Choose Tenant') }}
                        </button>
                    @endif
                    @if ($selectedTenant && admin_can_switch_tenant())
                        <form method="POST" action="{{ route('admin.tenant-context.destroy') }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-light">{{ __('Clear Selection') }}</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('admin.logout') }}">
                            @csrf
                            <button type="submit" class="btn btn-light">{{ __('Sign Out') }}</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@if (session('status'))
    <div class="row mt-3">
        <div class="col-12">
            <div class="alert alert-success">{{ session('status') }}</div>
        </div>
    </div>
@endif

@if (session('tenant_required'))
    <div class="row mt-3">
        <div class="col-12">
            <div class="alert alert-warning">{{ session('tenant_required') }}</div>
        </div>
    </div>
@endif

@endsection
