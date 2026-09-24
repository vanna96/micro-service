@extends('layouts.app')

@section('title', 'General Settings')
@section('page_title', 'General Settings')

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">
<style>
    /* Tab Styling */
    .general-settings-tabs .nav-link {
        color: #495057;
        font-weight: 500;
        padding: 0.95rem 1.4rem;
        border: none;
        border-bottom: 2px solid transparent;
        transition: all 0.2s ease-in-out;
        border-radius: 0;
        background: transparent;
    }
    .general-settings-tabs .nav-link:hover {
        color: #5b73e8;
        border-bottom-color: rgba(91, 115, 232, 0.4);
    }
    .general-settings-tabs .nav-link.active {
        color: #5b73e8;
        background-color: transparent;
        border-bottom: 2.5px solid #5b73e8;
        font-weight: 600;
    }
    .general-settings-tabs .nav-link .tab-icon {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-right: 10px;
        transition: all 0.2s ease;
    }
    .general-settings-tabs .nav-link.active .tab-icon.icon-mobile {
        background-color: rgba(91, 115, 232, 0.15);
        color: #5b73e8;
    }
    .general-settings-tabs .nav-link.active .tab-icon.icon-terms {
        background-color: rgba(23, 162, 184, 0.15);
        color: #17a2b8;
    }
    .general-settings-tabs .nav-link.active .tab-icon.icon-privacy {
        background-color: rgba(40, 167, 69, 0.15);
        color: #28a745;
    }

    /* Input & Card Accents */
    .input-group-icon-wrapper {
        position: relative;
    }
    .input-icon {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: #74788d;
        z-index: 5;
        font-size: 1.1rem;
    }
    .form-control-icon {
        padding-left: 42px !important;
    }

    /* Summernote Modernized Borders */
    .note-editor.note-frame {
        border: 1px solid #e2e5e8 !important;
        border-radius: 8px !important;
        box-shadow: none !important;
        overflow: hidden;
    }
    .note-editor.note-frame .note-toolbar {
        background-color: #f8f9fa !important;
        border-bottom: 1px solid #e9ecef !important;
        padding: 8px !important;
    }
    .note-editor.note-frame .note-statusbar {
        background-color: #fcfcfd !important;
        border-top: 1px solid #f1f3f5 !important;
    }
    .note-editor.note-frame .note-editing-area {
        background-color: #ffffff;
    }

    /* Version Rule Cards */
    .version-flow-item {
        border-left: 3px solid transparent;
        transition: all 0.2s ease;
    }
    .version-flow-item.flow-force {
        border-left-color: #f46a6a;
        background-color: rgba(244, 106, 106, 0.04);
    }
    .version-flow-item.flow-prompt {
        border-left-color: #f1b44c;
        background-color: rgba(241, 180, 76, 0.04);
    }
    .version-flow-item.flow-pass {
        border-left-color: #34c38f;
        background-color: rgba(52, 195, 143, 0.04);
    }

    /* Action bar */
    .settings-action-bar {
        background: #ffffff;
        border: 1px solid #e9e9ef;
        border-radius: 10px;
        box-shadow: 0 4px 12px rgba(39, 48, 78, 0.04);
    }
</style>
@endpush

@section('content')

{{-- Top Notification Alerts --}}
@if (session('status'))
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
        <div class="d-flex align-items-center">
            <div class="bg-success text-white rounded-circle p-2 me-3 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                <i class="uil uil-check fs-5"></i>
            </div>
            <div>
                <strong>Success!</strong> {{ session('status') }}
            </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
        <div class="d-flex">
            <i class="uil uil-exclamation-triangle fs-4 me-2"></i>
            <div>
                <strong>Please check the following errors:</strong>
                <ul class="mb-0 mt-1 ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

{{-- Header Banner Card --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
            <div class="d-flex align-items-center">
                <div class="avatar-md bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center me-3 shadow-xs">
                    <i class="uil uil-sliders-v-alt fs-2"></i>
                </div>
                <div>
                    <h4 class="mb-1 fw-bold text-dark">General Settings</h4>
                    <p class="text-muted mb-0 fs-13">
                        Configure version control, store distribution links, and legal documents for
                        <span class="badge bg-soft-primary text-primary px-2 py-1 ms-1">
                            <i class="mdi mdi-storefront-outline me-1"></i>{{ admin_tenant_display_name($selectedTenant) }}
                        </span>
                    </p>
                </div>
            </div>

            
        </div>
    </div>
</div>

{{-- Read-Only Notice --}}
@if (! $canEditGeneralSettings)
    <div class="alert alert-warning border-0 shadow-sm mb-4 d-flex align-items-center" role="alert">
        <i class="uil uil-lock me-2 fs-5"></i>
        <div>You have view-only access to general settings. Modifications are disabled.</div>
    </div>
@endif

{{-- Main Form Container --}}
<form id="general-settings-form" method="POST" action="{{ route('admin.general-settings.update') }}">
    @csrf
    @method('PUT')
    <fieldset @disabled(! $canEditGeneralSettings)>

    <div class="card border-0 shadow-sm overflow-hidden mb-4">
        {{-- Custom Modern Tabs Navigation --}}
        <div class="card-header bg-white border-bottom p-0">
            <ul class="nav nav-tabs general-settings-tabs border-bottom-0" id="generalSettingsTabList" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active d-flex align-items-center" 
                            id="tab-mobile-version" 
                            data-bs-toggle="tab" 
                            data-bs-target="#pane-mobile-version" 
                            type="button" 
                            role="tab" 
                            aria-controls="pane-mobile-version" 
                            aria-selected="true">
                        <div class="tab-icon icon-mobile bg-light text-muted">
                            <i class="uil uil-mobile-android fs-5"></i>
                        </div>
                        <div>
                            <span class="d-block">Mobile App Versioning</span>
                            <small class="text-muted fw-normal d-none d-sm-block">Update rules & app store links</small>
                        </div>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link d-flex align-items-center" 
                            id="tab-terms" 
                            data-bs-toggle="tab" 
                            data-bs-target="#pane-terms" 
                            type="button" 
                            role="tab" 
                            aria-controls="pane-terms" 
                            aria-selected="false">
                        <div class="tab-icon icon-terms bg-light text-muted">
                            <i class="uil uil-file-shield-alt fs-5"></i>
                        </div>
                        <div>
                            <span class="d-block">Terms & Conditions</span>
                            <small class="text-muted fw-normal d-none d-sm-block">Customer terms agreement</small>
                        </div>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link d-flex align-items-center" 
                            id="tab-privacy" 
                            data-bs-toggle="tab" 
                            data-bs-target="#pane-privacy" 
                            type="button" 
                            role="tab" 
                            aria-controls="pane-privacy" 
                            aria-selected="false">
                        <div class="tab-icon icon-privacy bg-light text-muted">
                            <i class="uil uil-shield-check fs-5"></i>
                        </div>
                        <div>
                            <span class="d-block">Privacy Policy</span>
                            <small class="text-muted fw-normal d-none d-sm-block">Data protection policy</small>
                        </div>
                    </button>
                </li>
            </ul>
        </div>

        {{-- Tab Content Panes --}}
        <div class="card-body p-4">
            <div class="tab-content" id="generalSettingsTabContent">
                
                {{-- TAB 1: MOBILE APP VERSION CONTROL --}}
                <div class="tab-pane fade show active" id="pane-mobile-version" role="tabpanel" aria-labelledby="tab-mobile-version">
                    <div class="row g-4">
                        {{-- Left: Version Form Inputs --}}
                        <div class="col-xl-7 col-lg-7">
                            <div class="mb-4">
                                <h5 class="fw-bold text-dark mb-1">Version Control Rules</h5>
                                <p class="text-muted fs-13">Define the versions that govern forced and recommended updates for mobile users.</p>
                            </div>

                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <div class="p-3 border rounded-3 bg-white h-100 position-relative">
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <label class="form-label fw-bold mb-0 text-dark" for="minimum_mobile_version">
                                                Minimum Version
                                            </label>
                                            <span class="badge bg-soft-danger text-danger px-2 py-1 rounded-pill fs-11">
                                                <i class="uil uil-shield-exclamation me-1"></i>Force Update
                                            </span>
                                        </div>
                                        <div class="input-group-icon-wrapper mb-2">
                                            <i class="uil uil-arrow-circle-up input-icon text-danger"></i>
                                            <input type="text" 
                                                   id="minimum_mobile_version" 
                                                   name="minimum_mobile_version" 
                                                   class="form-control form-control-icon @error('minimum_mobile_version') is-invalid @enderror"
                                                   placeholder="1.0.0" 
                                                   value="{{ old('minimum_mobile_version', $generalSettings['minimum_mobile_version'] ?? '1.0.0') }}">
                                        </div>
                                        @error('minimum_mobile_version')
                                            <div class="text-danger fs-12 mb-2">{{ $message }}</div>
                                        @enderror
                                        <div class="text-muted fs-12">
                                            Users running an app build <strong>lower</strong> than this version will be blocked until updated.
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="p-3 border rounded-3 bg-white h-100 position-relative">
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <label class="form-label fw-bold mb-0 text-dark" for="latest_mobile_version">
                                                Latest Version
                                            </label>
                                            <span class="badge bg-soft-success text-success px-2 py-1 rounded-pill fs-11">
                                                <i class="uil uil-cloud-check me-1"></i>Store Build
                                            </span>
                                        </div>
                                        <div class="input-group-icon-wrapper mb-2">
                                            <i class="uil uil-tag-alt input-icon text-success"></i>
                                            <input type="text" 
                                                   id="latest_mobile_version" 
                                                   name="latest_mobile_version" 
                                                   class="form-control form-control-icon @error('latest_mobile_version') is-invalid @enderror"
                                                   placeholder="1.0.0" 
                                                   value="{{ old('latest_mobile_version', $generalSettings['latest_mobile_version'] ?? '1.0.0') }}">
                                        </div>
                                        @error('latest_mobile_version')
                                            <div class="text-danger fs-12 mb-2">{{ $message }}</div>
                                        @enderror
                                        <div class="text-muted fs-12">
                                            The latest release available on public app stores. Used for optional update prompts.
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <h5 class="fw-bold text-dark mb-1">Store Distribution Links</h5>
                                <p class="text-muted fs-13">Direct links where mobile users will be redirected when clicking "Update Now".</p>
                            </div>

                            {{-- iOS App Store URL --}}
                            <div class="card border mb-3 bg-white">
                                <div class="card-body p-3">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-xs bg-dark text-white rounded-circle d-flex align-items-center justify-content-center me-2">
                                                <i class="uil uil-apple fs-6"></i>
                                            </div>
                                            <label class="form-label fw-semibold mb-0" for="store_url_ios">
                                                Apple App Store URL
                                            </label>
                                        </div>
                                        @if(!empty($generalSettings['store_url_ios']))
                                            <a href="{{ $generalSettings['store_url_ios'] }}" target="_blank" rel="noopener noreferrer" class="btn btn-xs btn-outline-dark fs-11 py-1 px-2 rounded">
                                                <i class="uil uil-external-link-alt me-1"></i> Test Link
                                            </a>
                                        @endif
                                    </div>
                                    <div class="input-group-icon-wrapper">
                                        <i class="uil uil-link input-icon"></i>
                                        <input type="url" 
                                               id="store_url_ios" 
                                               name="store_url_ios" 
                                               class="form-control form-control-icon @error('store_url_ios') is-invalid @enderror"
                                               placeholder="https://apps.apple.com/app/id..." 
                                               value="{{ old('store_url_ios', $generalSettings['store_url_ios'] ?? '') }}">
                                    </div>
                                    @error('store_url_ios')
                                        <div class="text-danger fs-12 mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            {{-- Google Play Store URL --}}
                            <div class="card border mb-3 bg-white">
                                <div class="card-body p-3">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-xs bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-2">
                                                <i class="uil uil-android fs-6"></i>
                                            </div>
                                            <label class="form-label fw-semibold mb-0" for="store_url_android">
                                                Google Play Store URL
                                            </label>
                                        </div>
                                        @if(!empty($generalSettings['store_url_android']))
                                            <a href="{{ $generalSettings['store_url_android'] }}" target="_blank" rel="noopener noreferrer" class="btn btn-xs btn-outline-success fs-11 py-1 px-2 rounded">
                                                <i class="uil uil-external-link-alt me-1"></i> Test Link
                                            </a>
                                        @endif
                                    </div>
                                    <div class="input-group-icon-wrapper">
                                        <i class="uil uil-link input-icon"></i>
                                        <input type="url" 
                                               id="store_url_android" 
                                               name="store_url_android" 
                                               class="form-control form-control-icon @error('store_url_android') is-invalid @enderror"
                                               placeholder="https://play.google.com/store/apps/details?id=..." 
                                               value="{{ old('store_url_android', $generalSettings['store_url_android'] ?? '') }}">
                                    </div>
                                    @error('store_url_android')
                                        <div class="text-danger fs-12 mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        {{-- Right: Update Logic Explainer & Simulator --}}
                        <div class="col-xl-5 col-lg-5">
                            <div class="card border bg-light h-100">
                                <div class="card-header bg-transparent border-bottom py-3">
                                    <div class="d-flex align-items-center">
                                        <i class="uil uil-info-circle text-primary fs-5 me-2"></i>
                                        <h6 class="mb-0 fw-bold text-dark">How Force Update Works</h6>
                                    </div>
                                </div>
                                <div class="card-body p-3">
                                    <p class="text-muted fs-13 mb-3">
                                        When the Flutter app launches, it queries the backend bootstrap API and evaluates the installed version against these rules:
                                    </p>

                                    <div class="d-flex flex-column gap-2 mb-4">
                                        {{-- Case 1: Force Update --}}
                                        <div class="version-flow-item flow-force p-3 rounded bg-white shadow-xs">
                                            <div class="d-flex align-items-center justify-content-between mb-1">
                                                <span class="fw-bold text-danger fs-13">
                                                    <i class="uil uil-times-circle me-1"></i> Force Update Modal
                                                </span>
                                                <span class="badge bg-danger text-white fs-11">App &lt; Min</span>
                                            </div>
                                            <p class="text-muted fs-12 mb-0">
                                                Non-dismissible screen. Blocks users completely until they tap "Update Now" and install the update.
                                            </p>
                                        </div>

                                        {{-- Case 2: Soft Prompt --}}
                                        <div class="version-flow-item flow-prompt p-3 rounded bg-white shadow-xs">
                                            <div class="d-flex align-items-center justify-content-between mb-1">
                                                <span class="fw-bold text-warning fs-13">
                                                    <i class="uil uil-bell me-1"></i> Optional Update Prompt
                                                </span>
                                                <span class="badge bg-warning text-white fs-11">Min &le; App &lt; Latest</span>
                                            </div>
                                            <p class="text-muted fs-12 mb-0">
                                                Dismissible dialog. Informs users about new features with "Update Now" and "Later" buttons.
                                            </p>
                                        </div>

                                        {{-- Case 3: Up-to-date --}}
                                        <div class="version-flow-item flow-pass p-3 rounded bg-white shadow-xs">
                                            <div class="d-flex align-items-center justify-content-between mb-1">
                                                <span class="fw-bold text-success fs-13">
                                                    <i class="uil uil-check-circle me-1"></i> App Up-to-Date
                                                </span>
                                                <span class="badge bg-success text-white fs-11">App &ge; Latest</span>
                                            </div>
                                            <p class="text-muted fs-12 mb-0">
                                                Normal silent startup. No update dialogs are displayed.
                                            </p>
                                        </div>
                                    </div>

                                    {{-- Quick Interactive Simulator --}}
                                    <div class="p-3 border rounded bg-white">
                                        <label class="form-label fw-bold text-dark fs-12 mb-1" for="sim_version_input">
                                            <i class="uil uil-flask text-primary me-1"></i> Live Behavior Preview
                                        </label>
                                        <div class="input-group input-group-sm mb-2">
                                            <span class="input-group-text bg-light text-muted fs-12">User App Version:</span>
                                            <input type="text" id="sim_version_input" class="form-control" placeholder="e.g. 1.0.0" value="1.0.0">
                                        </div>
                                        <div id="sim_result_badge" class="p-2 rounded text-center fs-12 fw-semibold">
                                            Evaluating...
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- TAB 2: TERMS & CONDITIONS --}}
                <div class="tab-pane fade" id="pane-terms" role="tabpanel" aria-labelledby="tab-terms">
                    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-3">
                        <div>
                            <h5 class="fw-bold text-dark mb-1">Terms & Conditions</h5>
                            <p class="text-muted fs-13 mb-0">
                                Provide the legal terms for your mobile app customers. Supports rich text, links, and lists.
                            </p>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-soft-info text-info px-3 py-1 fs-12 rounded-pill">
                                <i class="uil uil-eye me-1"></i> Visible in Mobile App Profile
                            </span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <textarea id="terms_conditions" 
                                  name="terms_conditions" 
                                  class="form-control @error('terms_conditions') is-invalid @enderror">{{ old('terms_conditions', $generalSettings['terms_conditions'] ?? '') }}</textarea>
                        @error('terms_conditions')
                            <div class="text-danger fs-12 mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="text-muted fs-12 d-flex align-items-center">
                        <i class="uil uil-info-circle me-1 text-primary"></i>
                        Content rendered using HTML in the customer's mobile application.
                    </div>
                </div>

                {{-- TAB 3: PRIVACY POLICY --}}
                <div class="tab-pane fade" id="pane-privacy" role="tabpanel" aria-labelledby="tab-privacy">
                    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-3">
                        <div>
                            <h5 class="fw-bold text-dark mb-1">Privacy Policy</h5>
                            <p class="text-muted fs-13 mb-0">
                                Disclose customer data handling, permissions, and privacy protections.
                            </p>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-soft-success text-success px-3 py-1 fs-12 rounded-pill">
                                <i class="uil uil-shield-check me-1"></i> Store & GDPR Compliant
                            </span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <textarea id="privacy_policy" 
                                  name="privacy_policy" 
                                  class="form-control @error('privacy_policy') is-invalid @enderror">{{ old('privacy_policy', $generalSettings['privacy_policy'] ?? '') }}</textarea>
                        @error('privacy_policy')
                            <div class="text-danger fs-12 mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="text-muted fs-12 d-flex align-items-center">
                        <i class="uil uil-info-circle me-1 text-primary"></i>
                        Required by Apple App Store and Google Play Store distribution guidelines.
                    </div>
                </div>

            </div>
        </div>
    </div>

    {{-- Bottom Action Bar --}}
    @if ($canEditGeneralSettings)
        <div class="settings-action-bar p-3 d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
            <div class="text-muted fs-13 d-flex align-items-center">
                <i class="uil uil-info-circle text-primary me-2 fs-5"></i>
                <span>All changes across versioning and legal documents are saved simultaneously.</span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button type="reset" class="btn btn-light px-3">
                    <i class="uil uil-redo me-1"></i> Reset
                </button>
                <button type="submit" class="btn btn-primary px-4 shadow-sm" id="btn-save-settings-bottom">
                    <i class="uil uil-check-circle me-1"></i> Save Settings
                </button>
            </div>
        </div>
    @endif

    </fieldset>
</form>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js"></script>
<script>
(function ($) {
    'use strict';

    // 1. Initialize Summernote Editors with polished toolbar & comfortable height
    var summernoteConfig = {
        height: 380,
        tabsize: 2,
        placeholder: 'Enter document content here...',
        toolbar: [
            ['style', ['style']],
            ['font', ['bold', 'italic', 'underline', 'clear']],
            ['color', ['color']],
            ['para', ['ul', 'ol', 'paragraph']],
            ['table', ['table']],
            ['insert', ['link', 'hr']],
            ['view', ['fullscreen', 'codeview', 'help']]
        ],
        callbacks: {
            onInit: function() {
                $('.note-editable').css('font-size', '14px');
                $('.note-editable').css('line-height', '1.6');
            }
        }
    };

    $('#terms_conditions').summernote(summernoteConfig);
    $('#privacy_policy').summernote(summernoteConfig);

    // 2. Remember Active Tab in URL Hash
    var hash = window.location.hash;
    if (hash) {
        var triggerEl = document.querySelector('#generalSettingsTabList button[data-bs-target="' + hash + '"]');
        if (triggerEl) {
            var tab = new bootstrap.Tab(triggerEl);
            tab.show();
        }
    }

    $('#generalSettingsTabList button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
        var target = $(e.target).attr('data-bs-target');
        if (history.pushState) {
            history.pushState(null, null, target);
        } else {
            location.hash = target;
        }
    });

    // 3. Live Version Logic Simulator
    function compareVersions(v1, v2) {
        var parts1 = (v1 || '0').split('.').map(function(n) { return parseInt(n, 10) || 0; });
        var parts2 = (v2 || '0').split('.').map(function(n) { return parseInt(n, 10) || 0; });
        var maxLen = Math.max(parts1.length, parts2.length);
        for (var i = 0; i < maxLen; i++) {
            var num1 = parts1[i] || 0;
            var num2 = parts2[i] || 0;
            if (num1 > num2) return 1;
            if (num1 < num2) return -1;
        }
        return 0;
    }

    function updateSimulation() {
        var minVer = $('#minimum_mobile_version').val().trim() || '1.0.0';
        var latestVer = $('#latest_mobile_version').val().trim() || '1.0.0';
        var userVer = $('#sim_version_input').val().trim() || '1.0.0';
        var $result = $('#sim_result_badge');

        if (compareVersions(userVer, minVer) < 0) {
            $result.attr('class', 'p-2 rounded text-center fs-12 fw-semibold bg-danger text-white')
                   .html('<i class="uil uil-times-circle me-1"></i> Mandatory Force Update Screen Shown');
        } else if (compareVersions(userVer, latestVer) < 0) {
            $result.attr('class', 'p-2 rounded text-center fs-12 fw-semibold bg-warning text-dark')
                   .html('<i class="uil uil-bell me-1"></i> Optional "Update Available" Prompt Shown');
        } else {
            $result.attr('class', 'p-2 rounded text-center fs-12 fw-semibold bg-success text-white')
                   .html('<i class="uil uil-check-circle me-1"></i> App is Up to Date (Silent Startup)');
        }
    }

    $('#minimum_mobile_version, #latest_mobile_version, #sim_version_input').on('input change', updateSimulation);
    updateSimulation();

    // 4. Form Submit Loading State
    $('#general-settings-form').on('submit', function() {
        var $btn = $(this).find('button[type="submit"]');
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Saving...');
    });

})(jQuery);
</script>
@endpush
