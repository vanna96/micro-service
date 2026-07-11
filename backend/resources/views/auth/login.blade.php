@extends('layouts.app')

@section('title', 'Admin Login')
@section('body_class', 'authentication-bg')

@php
    $activeScope = old('login_scope') === 'tenant' ? 'tenant' : 'administrator';
@endphp

@section('content')
<div class="account-pages my-5 pt-sm-5">
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="text-center">
                    <a href="{{ url('/') }}" class="mb-5 d-block auth-logo">
                        <img src="{{ asset('minible/assets/images/logo-dark.png') }}" alt="" height="22" class="logo logo-dark">
                        <img src="{{ asset('minible/assets/images/logo-light.png') }}" alt="" height="22" class="logo logo-light">
                    </a>
                </div>
            </div>
        </div>

        <div class="row align-items-center justify-content-center">
            <div class="col-md-8 col-lg-6 col-xl-5">
                <div class="card">
                    <div class="card-body p-4">
                        <div class="text-center mt-2">
                            <h5 class="text-primary">{{ __('Welcome Back!') }}</h5>
                            <p class="text-muted">{{ __('Choose how you want to sign in.') }}</p>
                        </div>

                        <div class="p-2 mt-4">
                            <ul class="nav nav-tabs nav-tabs-custom nav-justified mb-4" id="loginTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button
                                        id="administrator-login-tab"
                                        data-bs-toggle="tab"
                                        data-bs-target="#administrator-login-pane"
                                        type="button"
                                        role="tab"
                                        aria-controls="administrator-login-pane"
                                        aria-selected="{{ $activeScope === 'administrator' ? 'true' : 'false' }}"
                                        class="nav-link {{ $activeScope === 'administrator' ? 'active' : '' }}">
                                        <span class="d-block fw-semibold">{{ __('Administrator') }}</span>
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button
                                        id="tenant-login-tab"
                                        data-bs-toggle="tab"
                                        data-bs-target="#tenant-login-pane"
                                        type="button"
                                        role="tab"
                                        aria-controls="tenant-login-pane"
                                        aria-selected="{{ $activeScope === 'tenant' ? 'true' : 'false' }}"
                                        class="nav-link {{ $activeScope === 'tenant' ? 'active' : '' }}">
                                        <span class="d-block fw-semibold">{{ __('Tenant User') }}</span>
                                    </button>
                                </li>
                            </ul>

                            @if ($errors->has('login_scope'))
                                <div class="alert alert-danger">{{ $errors->first('login_scope') }}</div>
                            @endif

                            <div class="tab-content" id="loginTabsContent">
                                <div
                                    id="administrator-login-pane"
                                    role="tabpanel"
                                    aria-labelledby="administrator-login-tab"
                                    class="tab-pane fade {{ $activeScope === 'administrator' ? 'show active' : '' }}">
                                    <form method="POST" action="{{ route('admin.login.store') }}">
                                        @csrf
                                        <input type="hidden" name="login_scope" value="administrator">

                                        @if ($activeScope === 'administrator' && $errors->has('username'))
                                            <div class="alert alert-danger">{{ $errors->first('username') }}</div>
                                        @endif

                                        <div class="mb-3">
                                            <label class="form-label" for="administrator_username">{{ __('Username, Email or Phone') }}</label>
                                            <input
                                                type="text"
                                                class="form-control @if ($activeScope === 'administrator' && $errors->has('username')) is-invalid @endif"
                                                id="administrator_username"
                                                name="username"
                                                value="{{ $activeScope === 'administrator' ? old('username') : '' }}"
                                                placeholder="{{ __('Enter username, email or phone') }}"
                                                autofocus>
                                            <div class="form-text">{{ __('Administrators can sign in with username, email, or phone.') }}</div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label" for="administrator_password">{{ __('Password') }}</label>
                                            <input
                                                type="password"
                                                class="form-control @if ($activeScope === 'administrator' && $errors->has('password')) is-invalid @endif"
                                                id="administrator_password"
                                                name="password"
                                                placeholder="{{ __('Enter password') }}">
                                            @if ($activeScope === 'administrator')
                                                @error('password')
                                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                                @enderror
                                            @endif
                                        </div>

                                        <div class="form-check">
                                            <input
                                                class="form-check-input"
                                                type="checkbox"
                                                id="administrator_remember"
                                                name="remember"
                                                {{ $activeScope === 'administrator' && old('remember') ? 'checked' : '' }}>
                                            <label class="form-check-label" for="administrator_remember">{{ __('Remember me') }}</label>
                                        </div>

                                        <div class="mt-3 text-end">
                                            <button class="btn btn-primary w-sm waves-effect waves-light" type="submit">{{ __('Log In') }}</button>
                                        </div>
                                    </form>
                                </div>

                                <div
                                    id="tenant-login-pane"
                                    role="tabpanel"
                                    aria-labelledby="tenant-login-tab"
                                    class="tab-pane fade {{ $activeScope === 'tenant' ? 'show active' : '' }}">
                                    <form method="POST" action="{{ route('admin.login.store') }}">
                                        @csrf
                                        <input type="hidden" name="login_scope" value="tenant">

                                        @if ($activeScope === 'tenant' && $errors->has('username'))
                                            <div class="alert alert-danger">{{ $errors->first('username') }}</div>
                                        @endif

                                        <div class="mb-3">
                                            <label class="form-label" for="tenant_code">{{ __('Tenant Code') }}</label>
                                            <input
                                                type="text"
                                                class="form-control @if ($activeScope === 'tenant' && $errors->has('tenant_code')) is-invalid @endif"
                                                id="tenant_code"
                                                name="tenant_code"
                                                value="{{ $activeScope === 'tenant' ? old('tenant_code') : '' }}"
                                                placeholder="{{ __('Enter tenant code') }}">
                                            <div class="form-text">{{ __('Example: use the tenant ID such as shop-01 or tenant-a.') }}</div>
                                            @if ($activeScope === 'tenant')
                                                @error('tenant_code')
                                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                                @enderror
                                            @endif
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label" for="tenant_username">{{ __('Username') }}</label>
                                            <input
                                                type="text"
                                                class="form-control @if ($activeScope === 'tenant' && $errors->has('username')) is-invalid @endif"
                                                id="tenant_username"
                                                name="username"
                                                value="{{ $activeScope === 'tenant' ? old('username') : '' }}"
                                                placeholder="{{ __('Enter username') }}">
                                            <div class="form-text">{{ __('Enter the username from that tenant only.') }}</div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label" for="tenant_password">{{ __('Password') }}</label>
                                            <input
                                                type="password"
                                                class="form-control @if ($activeScope === 'tenant' && $errors->has('password')) is-invalid @endif"
                                                id="tenant_password"
                                                name="password"
                                                placeholder="{{ __('Enter password') }}">
                                            @if ($activeScope === 'tenant')
                                                @error('password')
                                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                                @enderror
                                            @endif
                                        </div>

                                        <div class="form-check">
                                            <input
                                                class="form-check-input"
                                                type="checkbox"
                                                id="tenant_remember"
                                                name="remember"
                                                {{ $activeScope === 'tenant' && old('remember') ? 'checked' : '' }}>
                                            <label class="form-check-label" for="tenant_remember">{{ __('Remember me') }}</label>
                                        </div>

                                        <div class="mt-3 text-end">
                                            <button class="btn btn-primary w-sm waves-effect waves-light" type="submit">{{ __('Log In') }}</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const storageKeys = {
                administrator: {
                    remember: 'admin-login:administrator:remember',
                    username: 'admin-login:administrator:username',
                },
                tenant: {
                    remember: 'admin-login:tenant:remember',
                    tenantCode: 'admin-login:tenant:tenant-code',
                    username: 'admin-login:tenant:username',
                },
            };
            const activeTabTrigger = document.querySelector('#loginTabs .nav-link.active');
            const administratorForm = document.querySelector('#administrator-login-pane form');
            const administratorUsername = document.getElementById('administrator_username');
            const administratorRemember = document.getElementById('administrator_remember');
            const tenantForm = document.querySelector('#tenant-login-pane form');
            const tenantCode = document.getElementById('tenant_code');
            const tenantUsername = document.getElementById('tenant_username');
            const tenantRemember = document.getElementById('tenant_remember');

            const safeStorage = {
                get(key) {
                    try {
                        return window.localStorage.getItem(key);
                    } catch (error) {
                        return null;
                    }
                },
                set(key, value) {
                    try {
                        window.localStorage.setItem(key, value);
                    } catch (error) {
                        // Ignore storage failures so login still works normally.
                    }
                },
                remove(key) {
                    try {
                        window.localStorage.removeItem(key);
                    } catch (error) {
                        // Ignore storage failures so login still works normally.
                    }
                },
            };

            const setInputValueIfEmpty = function (input, value) {
                if (!input || input.value.trim() !== '' || !value) {
                    return;
                }

                input.value = value;
            };

            const loadRememberedAdministrator = function () {
                if (!administratorRemember || safeStorage.get(storageKeys.administrator.remember) !== '1') {
                    return;
                }

                administratorRemember.checked = true;
                setInputValueIfEmpty(
                    administratorUsername,
                    safeStorage.get(storageKeys.administrator.username)
                );
            };

            const loadRememberedTenant = function () {
                if (!tenantRemember || safeStorage.get(storageKeys.tenant.remember) !== '1') {
                    return;
                }

                tenantRemember.checked = true;
                setInputValueIfEmpty(
                    tenantCode,
                    safeStorage.get(storageKeys.tenant.tenantCode)
                );
                setInputValueIfEmpty(
                    tenantUsername,
                    safeStorage.get(storageKeys.tenant.username)
                );
            };

            const clearAdministratorRememberedValues = function () {
                safeStorage.remove(storageKeys.administrator.remember);
                safeStorage.remove(storageKeys.administrator.username);
            };

            const clearTenantRememberedValues = function () {
                safeStorage.remove(storageKeys.tenant.remember);
                safeStorage.remove(storageKeys.tenant.tenantCode);
                safeStorage.remove(storageKeys.tenant.username);
            };

            if (administratorForm && administratorRemember && administratorUsername) {
                loadRememberedAdministrator();

                administratorRemember.addEventListener('change', function () {
                    if (!administratorRemember.checked) {
                        clearAdministratorRememberedValues();
                    }
                });

                administratorForm.addEventListener('submit', function () {
                    if (!administratorRemember.checked) {
                        clearAdministratorRememberedValues();

                        return;
                    }

                    safeStorage.set(storageKeys.administrator.remember, '1');
                    safeStorage.set(storageKeys.administrator.username, administratorUsername.value.trim());
                });
            }

            if (tenantForm && tenantRemember && tenantCode && tenantUsername) {
                loadRememberedTenant();

                tenantRemember.addEventListener('change', function () {
                    if (!tenantRemember.checked) {
                        clearTenantRememberedValues();
                    }
                });

                tenantForm.addEventListener('submit', function () {
                    if (!tenantRemember.checked) {
                        clearTenantRememberedValues();

                        return;
                    }

                    safeStorage.set(storageKeys.tenant.remember, '1');
                    safeStorage.set(storageKeys.tenant.tenantCode, tenantCode.value.trim());
                    safeStorage.set(storageKeys.tenant.username, tenantUsername.value.trim());
                });
            }

            if (!activeTabTrigger || typeof bootstrap === 'undefined') {
                return;
            }

            bootstrap.Tab.getOrCreateInstance(activeTabTrigger).show();
        });
    </script>
@endpush
