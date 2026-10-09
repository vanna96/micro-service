@extends('layouts.app')

@section('title', __('Translations & Localization'))
@section('page_title', __('Translations'))

@push('styles')
<style>
    .trans-badge-lang {
        font-size: 13px;
        padding: 5px 12px;
        border-radius: 20px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .sandbox-card {
        background: linear-gradient(145deg, #ffffff, #f8f9fa);
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.03);
    }
    .result-box {
        background: #f1f5f9;
        border: 1px dashed #cbd5e1;
        border-radius: 8px;
        min-height: 52px;
        display: flex;
        align-items: center;
        padding: 10px 16px;
        font-size: 15px;
        color: #1e293b;
    }
</style>
@endpush

@section('content')
<div class="row mb-3 align-items-center">
    <div class="col-md-6">
        <h4 class="mb-0 text-dark fw-bold">
            <i class="uil uil-language text-primary me-2"></i>{{ __('Translations & Localization') }}
        </h4>
        <p class="text-muted mb-0 font-size-13">{{ __('Manage system dictionaries, switch interface languages, and test live backend translations.') }}</p>
    </div>
    <div class="col-md-6 text-md-end mt-3 mt-md-0">
        <div class="d-inline-flex align-items-center gap-2">
            <span class="text-muted font-size-13 me-1">{{ __('Current Language:') }}</span>
            <div class="btn-group" role="group">
                <a href="{{ route('locale.switch', 'kh') }}" class="btn btn-sm {{ in_array($activeLocale, ['kh', 'km']) ? 'btn-primary' : 'btn-outline-secondary' }}">
                    🇰🇭 ខ្មែរ (KH)
                </a>
                <a href="{{ route('locale.switch', 'en') }}" class="btn btn-sm {{ $activeLocale === 'en' ? 'btn-primary' : 'btn-outline-secondary' }}">
                    🇬🇧 EN
                </a>
            </div>
            <form action="{{ route('admin.translations.clear-cache') }}" method="POST" class="d-inline ms-2">
                @csrf
                <button type="submit" class="btn btn-sm btn-light border text-secondary" title="{{ __('Clear Translation Cache') }}">
                    <i class="uil uil-sync me-1"></i>{{ __('Clear Cache') }}
                </button>
            </form>
        </div>
    </div>
</div>

@if (session('status'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="uil uil-check-circle me-2"></i>{{ __(session('status')) }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<!-- Overview Cards -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="avatar-sm rounded-circle bg-soft-primary text-primary d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                    <i class="uil uil-globe font-size-24"></i>
                </div>
                <div>
                    <div class="text-muted font-size-13">{{ __('Active Interface Language') }}</div>
                    <div class="fw-bold font-size-18 text-dark mt-1">
                        @if (in_array($activeLocale, ['kh', 'km']))
                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2.5 py-1.5">
                                🇰🇭 ភាសាខ្មែរ (KH)
                            </span>
                        @else
                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2.5 py-1.5">
                                🇬🇧 English (EN)
                            </span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="avatar-sm rounded-circle bg-soft-info text-info d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                    <i class="uil uil-book-open font-size-24"></i>
                </div>
                <div>
                    <div class="text-muted font-size-13">{{ __('Khmer Dictionary Entries') }}</div>
                    <div class="fw-bold font-size-20 text-dark mt-1">{{ number_format($totalCount) }} <span class="font-size-13 fw-normal text-muted">{{ __('keys') }}</span></div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="avatar-sm rounded-circle {{ $serviceOnline ? 'bg-soft-success text-success' : 'bg-soft-warning text-warning' }} d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                    <i class="uil uil-server-network font-size-24"></i>
                </div>
                <div>
                    <div class="text-muted font-size-13">{{ __('AI / Microservice Engine') }}</div>
                    <div class="mt-1">
                        @if ($serviceOnline)
                            <span class="badge bg-success"><i class="uil uil-check me-1"></i>{{ __('Online & Active') }}</span>
                        @else
                            <span class="badge bg-info"><i class="uil uil-database me-1"></i>{{ __('Local Dictionary Engine') }}</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Interactive Live Translation Sandbox -->
<div class="card shadow-sm border-0 mb-4 sandbox-card">
    <div class="card-body p-4">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h5 class="card-title mb-1 fw-bold text-dark">
                    <i class="uil uil-bolt-alt text-warning me-1.5"></i>{{ __('Live Translation Sandbox') }}
                </h5>
                <p class="text-muted font-size-13 mb-0">{{ __('Test real-time backend translation for any word, phrase, or error message.') }}</p>
            </div>
            <span class="badge bg-soft-primary text-primary font-size-12 px-2.5 py-1">{{ __('Backend Live Tool') }}</span>
        </div>

        <div class="row g-3">
            <div class="col-md-5">
                <label class="form-label fw-semibold text-secondary font-size-13" for="sandbox-input">{{ __('Source Text (English or Any)') }}</label>
                <textarea id="sandbox-input" class="form-control" rows="2" placeholder="e.g. Branch created successfully or Welcome to V-POS"></textarea>
            </div>

            <div class="col-md-2 d-flex flex-column justify-content-center align-items-center">
                <label class="form-label fw-semibold text-secondary font-size-13 text-center mb-1" for="sandbox-lang">{{ __('Target') }}</label>
                <select id="sandbox-lang" class="form-select form-select-sm mb-2 text-center" style="max-width: 140px;">
                    <option value="kh" selected>🇰🇭 Khmer (KH)</option>
                    <option value="en">🇬🇧 English (EN)</option>
                </select>
                <button type="button" id="sandbox-btn" class="btn btn-primary btn-sm px-3 w-100" style="max-width: 140px;">
                    <i class="uil uil-arrow-right me-1"></i>{{ __('Translate') }}
                </button>
            </div>

            <div class="col-md-5">
                <label class="form-label fw-semibold text-secondary font-size-13">{{ __('Khmer Translation Output') }}</label>
                <div id="sandbox-output" class="result-box">
                    <span class="text-muted font-italic font-size-13">{{ __('Click Translate to see backend response...') }}</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Translation Dictionary Table -->
<div class="card shadow-sm border-0">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
            <div>
                <h5 class="card-title mb-1 fw-bold text-dark">{{ __('Khmer Translation Dictionary (lang/kh.json)') }}</h5>
                <p class="text-muted font-size-13 mb-0">{{ __('Showing :count entries', ['count' => count($translations)]) }}</p>
            </div>

            <div class="d-flex align-items-center gap-2">
                <form action="{{ route('admin.translations.index') }}" method="GET" class="d-flex gap-2">
                    <div class="input-group input-group-sm" style="width: 260px;">
                        <input type="text" name="search" class="form-control" placeholder="{{ __('Search key or translation...') }}" value="{{ $search }}">
                        <button class="btn btn-light border" type="submit"><i class="uil uil-search"></i></button>
                        @if ($search)
                            <a href="{{ route('admin.translations.index') }}" class="btn btn-outline-secondary" title="{{ __('Clear search') }}"><i class="uil uil-times"></i></a>
                        @endif
                    </div>
                </form>
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addTranslationModal">
                    <i class="uil uil-plus me-1"></i>{{ __('Add Key') }}
                </button>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 border">
                <thead class="table-light">
                    <tr>
                        <th style="width: 45%;">{{ __('Original English Phrase (Key)') }}</th>
                        <th style="width: 45%;">{{ __('Khmer Translation (KH)') }}</th>
                        <th class="text-end" style="width: 10%;">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($translations as $key => $val)
                        <tr>
                            <td>
                                <span class="fw-semibold text-dark font-monospace font-size-13">{{ $key }}</span>
                            </td>
                            <td>
                                <span class="text-primary fw-medium font-size-14">{{ $val }}</span>
                            </td>
                            <td class="text-end text-nowrap">
                                <button type="button" class="btn btn-sm btn-light border py-1 px-2 js-edit-btn"
                                    data-key="{{ $key }}" data-val="{{ $val }}" title="{{ __('Edit') }}">
                                    <i class="uil uil-edit font-size-14 text-muted"></i>
                                </button>
                                <form action="{{ route('admin.translations.destroy') }}" method="POST" class="d-inline js-delete-translation"
                                    data-confirm="{{ __('Delete this translation?') }}">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="key" value="{{ $key }}">
                                    <button type="submit" class="btn btn-sm btn-light border py-1 px-2" title="{{ __('Delete') }}" aria-label="{{ __('Delete') }}">
                                        <i class="uil uil-trash-alt font-size-14 text-danger"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center py-4 text-muted">
                                <i class="uil uil-search-alt font-size-24 d-block mb-1"></i>
                                {{ __('No translation entries found.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Add/Edit Translation -->
<div class="modal fade" id="addTranslationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ route('admin.translations.update') }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="modalTitle">{{ __('Add / Edit Translation') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold" for="modal-key">{{ __('English Phrase (Exact Match Key)') }}</label>
                    <textarea name="key" id="modal-key" class="form-control" rows="2" required placeholder="e.g. Branch created successfully."></textarea>
                    <small class="text-muted">{{ __('This exact string will be translated when passed to __() or translate().') }}</small>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold" for="modal-value">{{ __('Khmer Translation (KH)') }}</label>
                    <textarea name="value" id="modal-value" class="form-control" rows="2" required placeholder="e.g. បានបង្កើតសាខាដោយជោគជ័យ។"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="submit" class="btn btn-primary"><i class="uil uil-check me-1"></i>{{ __('Save Translation') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.js-delete-translation').forEach(form => {
        form.addEventListener('submit', function (event) {
            if (!window.confirm(this.dataset.confirm)) {
                event.preventDefault();
            }
        });
    });

    // Edit Modal Trigger
    const editBtns = document.querySelectorAll('.js-edit-btn');
    const modalKey = document.getElementById('modal-key');
    const modalValue = document.getElementById('modal-value');
    const modalTitle = document.getElementById('modalTitle');
    const modal = new bootstrap.Modal(document.getElementById('addTranslationModal'));

    editBtns.forEach(btn => {
        btn.addEventListener('click', function () {
            modalKey.value = this.dataset.key;
            modalValue.value = this.dataset.val;
            modalTitle.textContent = '{{ __('Edit Translation') }}';
            modal.show();
        });
    });

    // Reset modal on new add
    document.querySelector('[data-bs-target="#addTranslationModal"]').addEventListener('click', function () {
        modalKey.value = '';
        modalValue.value = '';
        modalTitle.textContent = '{{ __('Add Translation') }}';
    });

    // Live Sandbox Ajax
    const sandboxBtn = document.getElementById('sandbox-btn');
    const sandboxInput = document.getElementById('sandbox-input');
    const sandboxLang = document.getElementById('sandbox-lang');
    const sandboxOutput = document.getElementById('sandbox-output');

    sandboxBtn.addEventListener('click', async function () {
        const text = sandboxInput.value.trim();
        if (!text) {
            sandboxInput.focus();
            return;
        }

        sandboxBtn.disabled = true;
        sandboxBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>...';
        sandboxOutput.innerHTML = '<span class="text-muted font-italic">Translating...</span>';

        try {
            const res = await fetch('{{ route('admin.translations.test') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    text: text,
                    lng: sandboxLang.value
                })
            });

            const data = await res.json();
            if (data.success) {
                sandboxOutput.innerHTML = `<span class="fw-bold text-primary font-size-15">${data.translated}</span>`;
            } else {
                sandboxOutput.innerHTML = `<span class="text-danger font-size-13">${data.message || 'Translation failed.'}</span>`;
            }
        } catch (err) {
            sandboxOutput.innerHTML = '<span class="text-danger font-size-13">Error communicating with backend.</span>';
        } finally {
            sandboxBtn.disabled = false;
            sandboxBtn.innerHTML = '<i class="uil uil-arrow-right me-1"></i>{{ __('Translate') }}';
        }
    });
});
</script>
@endpush
