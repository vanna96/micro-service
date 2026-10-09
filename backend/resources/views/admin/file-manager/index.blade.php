@extends('layouts.app')

@section('title', 'File Manager')
@section('page_title', 'File Manager')

@push('styles')
<link href="{{ global_asset('minible/assets/libs/datatables.net-bs4/css/dataTables.bootstrap4.min.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ global_asset('minible/assets/libs/datatables.net-responsive-bs4/css/responsive.bootstrap4.min.css') }}" rel="stylesheet" type="text/css" />
<style>
    .file-manager-shell {
        display: grid;
        gap: 18px;
        grid-template-columns: 260px minmax(0, 1fr) 250px;
    }

    .file-manager-panel {
        background: #fff;
        border: 1px solid #e9edf5;
        border-radius: 18px;
        box-shadow: 0 10px 30px rgba(25, 42, 70, 0.06);
    }

    .file-manager-sidebar,
    .file-manager-rail {
        padding: 14px;
    }

    .file-manager-main {
        padding: 16px;
    }

    .file-manager-create-button {
        width: 100%;
        border: 0;
        border-radius: 12px;
        padding: 14px 16px;
        background: linear-gradient(135deg, #5164e2 0%, #5d74f3 100%);
        color: #fff;
        font-weight: 600;
        box-shadow: 0 14px 30px rgba(81, 100, 226, 0.24);
    }

    .file-manager-create-pane {
        border: 1px dashed #d7ddf1;
        border-radius: 14px;
        background: #f8faff;
    }

    .file-manager-nav-section-title {
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #8a94ad;
    }

    .file-manager-nav-link {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 10px 12px;
        border-radius: 12px;
        color: #43506a;
        text-decoration: none;
        transition: background-color 0.15s ease, color 0.15s ease;
    }

    .file-manager-nav-link:hover,
    .file-manager-nav-link.active {
        background: #f2f5fb;
        color: #27324d;
    }

    .file-manager-main-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 18px;
        margin-bottom: 18px;
    }

    .file-manager-kicker {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 6px 10px;
        border-radius: 999px;
        background: #eef3ff;
        color: #5164e2;
        font-size: 12px;
        font-weight: 600;
        margin-bottom: 10px;
    }

    .file-manager-breadcrumbs {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .file-manager-breadcrumb {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 12px;
        border-radius: 999px;
        background: #f5f7fc;
        color: #51607d;
        text-decoration: none;
        font-size: 12px;
        font-weight: 600;
    }

    .file-manager-breadcrumb.active {
        background: #5164e2;
        color: #fff;
    }

    .file-manager-storage-grid,
    .file-manager-folder-grid,
    .file-manager-context-grid {
        display: grid;
        gap: 14px;
    }

    .file-manager-storage-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
        margin-bottom: 18px;
    }

    .file-manager-folder-grid,
    .file-manager-context-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
        margin-bottom: 18px;
    }

    .file-manager-storage-card,
    .file-manager-folder-card,
    .file-manager-context-card {
        border: 1px solid #edf1f7;
        border-radius: 16px;
        padding: 16px;
        background: #fff;
        transition: transform 0.15s ease, box-shadow 0.15s ease;
    }

    .file-manager-folder-card:hover,
    .file-manager-storage-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 24px rgba(31, 50, 81, 0.08);
    }

    .file-manager-storage-icon,
    .file-manager-folder-icon,
    .file-manager-file-preview {
        width: 42px;
        height: 42px;
        border-radius: 12px;
        background: #eef3ff;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .file-manager-file-preview {
        width: 46px;
        height: 46px;
        overflow: hidden;
        background: #f5f7fc;
    }

    .file-manager-file-preview img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .file-manager-progress {
        height: 6px;
        border-radius: 999px;
        background: #edf1f7;
        overflow: hidden;
    }

    .file-manager-progress > span {
        display: block;
        height: 100%;
        border-radius: inherit;
        background: linear-gradient(90deg, #5a6ff0 0%, #6f82ff 100%);
    }

    .file-manager-section-title {
        font-size: 18px;
        font-weight: 700;
        color: #303a52;
        margin-bottom: 14px;
    }

    .file-manager-mini-chart {
        height: 170px;
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 12px;
        padding: 18px 8px 6px;
        border-bottom: 1px dashed #e7ecf5;
    }

    .file-manager-mini-bar {
        flex: 1;
        text-align: center;
    }

    .file-manager-mini-bar-column {
        width: 100%;
        border-radius: 12px 12px 0 0;
        min-height: 10px;
    }

    .file-manager-type-list,
    .file-manager-sidebar-list {
        display: grid;
        gap: 10px;
    }

    .file-manager-type-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 10px 12px;
        border-radius: 14px;
        background: #f8faff;
    }

    .file-manager-upload-card {
        border: 1px dashed #d7ddf1;
        border-radius: 16px;
        background: linear-gradient(180deg, #fbfcff 0%, #f6f8fd 100%);
        text-align: center;
        padding: 20px 16px;
    }

    .file-manager-recent-table thead th {
        border-top: 0;
        font-size: 12px;
        color: #7a859d;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .file-manager-recent-table tbody td {
        padding-top: 14px;
        padding-bottom: 14px;
        vertical-align: middle;
    }

    .file-manager-action-link {
        color: #5a6ff0;
        text-decoration: none;
        font-weight: 600;
        font-size: 13px;
    }

    .file-manager-accent-primary {
        background: #eef1ff;
        color: #5164e2;
    }

    .file-manager-accent-success {
        background: #ebfbf4;
        color: #27b579;
    }

    .file-manager-accent-warning {
        background: #fff5e7;
        color: #f0a43a;
    }

    .file-manager-accent-danger {
        background: #ffeef0;
        color: #ef5a6f;
    }

    .file-manager-accent-info {
        background: #edf7ff;
        color: #4395ff;
    }

    .file-manager-accent-secondary {
        background: #f1f3f8;
        color: #7b879c;
    }

    @media (max-width: 1399.98px) {
        .file-manager-shell {
            grid-template-columns: 240px minmax(0, 1fr);
        }

        .file-manager-rail {
            grid-column: 1 / -1;
        }
    }

    @media (max-width: 991.98px) {
        .file-manager-shell,
        .file-manager-storage-grid,
        .file-manager-folder-grid,
        .file-manager-context-grid {
            grid-template-columns: 1fr;
        }

        .file-manager-main-header {
            flex-direction: column;
        }
    }
</style>
@endpush

@section('content')
@php($canCreateFiles = admin_has_permission('file_manager.create'))
@php($canEditFiles = admin_has_permission('file_manager.edit'))
@php($canDeleteFiles = admin_has_permission('file_manager.delete'))
@php($canUploadCurrentSource = $canCreateFiles && $listing['current_source_uploadable'])
@php($canCreateFolders = $canCreateFiles && $listing['current_source_supports_folders'])
@php($canReplaceFromMyFiles = $canEditFiles && $canDeleteFiles && $listing['replacement_sources']->isNotEmpty())
<div class="file-manager-shell">
    <aside class="file-manager-panel file-manager-sidebar">
        @if ($canUploadCurrentSource || $canCreateFolders)
            <button class="file-manager-create-button mb-3" type="button" data-bs-toggle="collapse" data-bs-target="#fileManagerCreatePane" aria-expanded="false" aria-controls="fileManagerCreatePane">
                <i class="uil-plus me-2"></i>Create New
            </button>
            <div class="collapse mb-4" id="fileManagerCreatePane">
                <div class="file-manager-create-pane p-3">
                    @if ($canCreateFolders)
                        <form method="POST" action="{{ route('admin.file-manager.folders.store') }}" class="mb-3">
                            @csrf
                            <input type="hidden" name="current_directory" value="{{ $listing['current_directory'] }}">
                            <label class="form-label small text-muted fw-semibold">New folder</label>
                            <input type="text" name="folder_name" class="form-control form-control-sm @error('folder_name') is-invalid @enderror" placeholder="design-assets" required>
                            <button type="submit" class="btn btn-sm btn-outline-primary w-100 mt-2">Create Folder</button>
                        </form>
                    @endif

                    @if ($canUploadCurrentSource)
                        <form method="POST" action="{{ route('admin.file-manager.store') }}" enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="current_directory" value="{{ $listing['current_directory'] }}">
                            <label class="form-label small text-muted fw-semibold">Upload files</label>
                            <input type="file" name="files[]" class="form-control form-control-sm @error('files') is-invalid @enderror @error('files.*') is-invalid @enderror" multiple required>
                            <button type="submit" class="btn btn-sm btn-primary w-100 mt-2">Upload</button>
                        </form>
                    @endif
                </div>
            </div>
        @endif

        <div class="mb-4">
            <div class="file-manager-nav-section-title mb-2">Workspace</div>
            <div class="file-manager-sidebar-list">
                <a href="{{ route('admin.file-manager.index') }}" class="file-manager-nav-link {{ $listing['current_directory'] === '' ? 'active' : '' }}">
                    <span><i class="uil-folder me-2 text-warning"></i>My Files</span>
                    <span class="small text-muted">{{ $listing['total_files_count'] }}</span>
                </a>
                @foreach ($listing['directories'] as $directory)
                    <a href="{{ route('admin.file-manager.index', ['directory' => $directory['path']]) }}" class="file-manager-nav-link {{ $listing['current_directory'] === $directory['path'] ? 'active' : '' }}">
                        <span><i class="{{ ($directory['source'] ?? 'uploads') === 'uploads' ? 'uil-folder text-warning' : 'uil-image text-info' }} me-2"></i>{{ $directory['name'] }}</span>
                        <span class="small text-muted">{{ $directory['file_count'] }}</span>
                    </a>
                @endforeach
            </div>
        </div>

        <div class="mb-4">
            <div class="file-manager-nav-section-title mb-2">Tenant</div>
            <div class="file-manager-panel border-0 shadow-none bg-light-subtle p-3">
                <div class="fw-semibold">{{ admin_tenant_display_name($selectedTenant) }}</div>
                <div class="text-muted font-size-13 mt-1">{{ $listing['current_source_label'] }}</div>
                <div class="text-muted font-size-12 mt-3">Current directory</div>
                <div class="fw-semibold">{{ $listing['current_directory'] !== '' ? $listing['current_directory'] : 'Root' }}</div>
            </div>
        </div>

        <div class="file-manager-panel border-0 shadow-none p-3" style="background: linear-gradient(180deg, #fbfcff 0%, #f2f5ff 100%);">
            <div class="d-flex align-items-center justify-content-center mb-3" style="height: 110px;">
                <i class="uil-cloud-upload font-size-56 text-primary"></i>
            </div>
            <div class="text-center">
                <h5 class="mb-2">Organize Assets</h5>
                <p class="text-muted font-size-13 mb-0">Keep banners, documents, and campaign files in one tenant-safe workspace.</p>
            </div>
        </div>
    </aside>

    <main class="file-manager-panel file-manager-main">
        <div class="file-manager-main-header">
            <div>
                <div class="file-manager-kicker">
                    <i class="uil-folder-open"></i>
                    File Manager
                </div>
                <h3 class="mb-2">My Files</h3>
                <p class="text-muted mb-0">
                    Browse folders, all files, and tenant-scoped public file links in one place.
                    @if (! $listing['current_source_supports_folders'])
                        This library does not use folders, but you can still manage its files here.
                    @endif
                </p>
            </div>
            <div class="text-lg-end">
                <div class="file-manager-breadcrumbs">
                    @foreach ($listing['breadcrumbs'] as $crumb)
                        @if ($crumb['active'])
                            <span class="file-manager-breadcrumb active">{{ $crumb['label'] }}</span>
                        @else
                            <a href="{{ route('admin.file-manager.index', $crumb['path'] === '' ? [] : ['directory' => $crumb['path']]) }}" class="file-manager-breadcrumb">
                                {{ $crumb['label'] }}
                            </a>
                        @endif
                    @endforeach
                </div>
                @if (! is_null($listing['parent_directory']))
                    <a href="{{ route('admin.file-manager.index', $listing['parent_directory'] === '' ? [] : ['directory' => $listing['parent_directory']]) }}" class="btn btn-sm btn-outline-secondary mt-3">
                        <i class="uil-arrow-up-left me-1"></i>Up One Level
                    </a>
                @endif
            </div>
        </div>

        @if (session('status'))
            <div class="alert alert-success alert-border-left alert-dismissible fade show mb-4" role="alert">
                <i class="mdi mdi-check-all me-2"></i>{{ __(session('status')) }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if (session('error') || ($listing['storage_error'] ?? null))
            <div class="alert alert-danger alert-border-left alert-dismissible fade show mb-4" role="alert">
                <i class="mdi mdi-alert-circle-outline me-2"></i>{{ __(session('error') ?: $listing['storage_error']) }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <div class="file-manager-storage-grid">
            @foreach ($listing['summary_cards'] as $card)
                <article class="file-manager-storage-card">
                    <div class="d-flex align-items-start justify-content-between gap-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="file-manager-storage-icon file-manager-accent-{{ $card['accent'] }}">
                                <i class="{{ $card['icon'] }} font-size-24"></i>
                            </div>
                            <div>
                                <div class="fw-semibold">{{ $card['label'] }}</div>
                                <a href="{{ route('admin.file-manager.index', $listing['current_directory'] === '' ? [] : ['directory' => $listing['current_directory']]) }}" class="file-manager-action-link">View Folder</a>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-light rounded-circle">
                            <i class="uil-ellipsis-v"></i>
                        </button>
                    </div>
                    <div class="d-flex justify-content-between text-muted font-size-12 mt-3 mb-2">
                        <span>{{ $card['file_count'] }} files</span>
                        <span>{{ $card['storage_label'] }}</span>
                    </div>
                    <div class="file-manager-progress">
                        <span style="width: {{ $card['progress'] }}%"></span>
                    </div>
                </article>
            @endforeach
        </div>

        @if ($listing['directories']->isNotEmpty())
            <section class="mb-4">
                <h4 class="file-manager-section-title">Folders</h4>
                <div class="file-manager-folder-grid">
                    @foreach ($listing['directories'] as $directory)
                        <article class="file-manager-folder-card">
                            <div class="d-flex align-items-start justify-content-between gap-3">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="file-manager-folder-icon {{ ($directory['source'] ?? 'uploads') === 'uploads' ? 'file-manager-accent-warning' : 'file-manager-accent-info' }}">
                                        <i class="{{ ($directory['source'] ?? 'uploads') === 'uploads' ? 'uil-folder' : 'uil-image' }} font-size-24"></i>
                                    </div>
                                    <div>
                                        <div class="fw-semibold">{{ $directory['name'] }}</div>
                                        <div class="text-muted font-size-13">{{ $directory['file_count'] }} files</div>
                                    </div>
                                </div>
                                @if ($canDeleteFiles && ($directory['writable'] ?? false))
                                    <form method="POST" action="{{ route('admin.file-manager.destroy') }}" onsubmit="return confirm('Delete this folder and all files inside it?');">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="current_directory" value="{{ $listing['current_directory'] }}">
                                        <input type="hidden" name="path" value="{{ $directory['path'] }}">
                                        <input type="hidden" name="entry_type" value="directory">
                                        <button type="submit" class="btn btn-sm btn-light rounded-circle">
                                            <i class="uil-trash-alt text-danger"></i>
                                        </button>
                                    </form>
                                @endif
                            </div>
                            <div class="d-flex justify-content-between align-items-center mt-4">
                                <a href="{{ route('admin.file-manager.index', ['directory' => $directory['path']]) }}" class="file-manager-action-link">View Folder</a>
                                <span class="text-muted font-size-12"><i class="uil-clock-three me-1"></i>{{ $directory['updated_label'] }}</span>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="mb-4">
            <h4 class="file-manager-section-title">Workspace</h4>
            <div class="file-manager-context-grid">
                <article class="file-manager-context-card">
                    <div class="text-muted font-size-12 text-uppercase fw-semibold">Directory</div>
                    <div class="fw-semibold mt-2">{{ $listing['current_directory'] !== '' ? $listing['current_directory'] : 'Root Folder' }}</div>
                    <div class="text-muted font-size-13 mt-2">Use folders to separate banners, documents, and shared downloads.</div>
                </article>
                <article class="file-manager-context-card">
                    <div class="text-muted font-size-12 text-uppercase fw-semibold">Total Files</div>
                    <div class="fw-semibold mt-2">{{ $listing['total_files_count'] }} items</div>
                    <div class="text-muted font-size-13 mt-2">Counts every file in this level and its child folders.</div>
                </article>
                <article class="file-manager-context-card">
                    <div class="text-muted font-size-12 text-uppercase fw-semibold">Storage Used</div>
                    <div class="fw-semibold mt-2">{{ $listing['total_storage_label'] }}</div>
                    <div class="text-muted font-size-13 mt-2">Public URLs stay scoped under the selected tenant library.</div>
                </article>
            </div>
        </section>

        <section>
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <h4 class="file-manager-section-title mb-0">All Files</h4>
                @if ($canDeleteFiles)
                    <form id="bulkDeleteFilesForm" method="POST" action="{{ route('admin.file-manager.bulk-destroy') }}">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="current_directory" value="{{ $listing['current_directory'] }}">
                        <div id="bulkDeleteFilePaths"></div>
                        <button type="submit" id="bulkDeleteFilesButton" class="btn btn-sm btn-danger" disabled>
                            <i class="uil-trash-alt me-1"></i>Delete Selected (<span id="bulkDeleteFilesCount">0</span>)
                        </button>
                    </form>
                @endif
            </div>
            <div class="table-responsive">
                <table id="datatable-file-manager" class="table file-manager-recent-table align-middle dt-responsive nowrap w-100">
                    <thead>
                        <tr>
                            <th>
                                @if ($canDeleteFiles)
                                    <input type="checkbox" id="selectAllFiles" class="form-check-input" aria-label="Select all filtered files">
                                @endif
                            </th>
                            <th>Name</th>
                            <th>Date Modified</th>
                            <th>Size</th>
                            <th>Directory</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($listing['all_files'] as $file)
                            <tr>
                                <td>
                                    @if ($canDeleteFiles && $file['deletable'])
                                        <input
                                            type="checkbox"
                                            class="form-check-input"
                                            data-file-select
                                            value="{{ $file['path'] }}"
                                            aria-label="Select {{ $file['name'] }}"
                                        >
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="file-manager-file-preview">
                                            @if ($file['is_image'])
                                                <img src="{{ $file['url'] }}" alt="{{ $file['name'] }}">
                                            @else
                                                <i class="uil-file font-size-24 text-secondary"></i>
                                            @endif
                                        </div>
                                        <div>
                                            <div class="fw-semibold">{{ $file['name'] }}</div>
                                            <div class="text-muted font-size-12">{{ $file['source_label'] }} · {{ $file['mime_type'] }}</div>
                                            @if (($file['source'] ?? 'uploads') !== 'uploads')
                                                @if ($file['attached'] ?? false)
                                                    <div class="text-success font-size-12">Linked: {{ $file['owner_label'] }}</div>
                                                @else
                                                    <div class="text-danger font-size-12">Unassigned database image</div>
                                                @endif
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td data-order="{{ $file['updated_timestamp'] }}">{{ $file['updated_label'] }}</td>
                                <td>{{ $file['size_label'] }}</td>
                                <td>{{ str_contains($file['path'], '/') ? dirname($file['path']) : $file['source_label'] }}</td>
                                <td class="text-nowrap">
                                    <a href="{{ $file['url'] }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary me-2">Open</a>
                                    <button type="button" class="btn btn-sm btn-outline-secondary me-2" data-copy-url="{{ $file['url'] }}">Copy Link</button>
                                    @if ($canEditFiles && ($file['renameable'] ?? false))
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-info me-2"
                                            data-bs-toggle="modal"
                                            data-bs-target="#renameFileModal"
                                            data-rename-path="{{ $file['path'] }}"
                                            data-rename-name="{{ $file['name'] }}"
                                        >Rename</button>
                                    @endif
                                    @if ($canReplaceFromMyFiles && ($file['replaceable'] ?? false))
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-success me-2"
                                            data-bs-toggle="modal"
                                            data-bs-target="#replaceFileModal"
                                            data-replace-path="{{ $file['path'] }}"
                                            data-replace-name="{{ $file['name'] }}"
                                            data-replace-owner="{{ $file['owner_label'] }}"
                                            data-replace-extension="{{ strtolower($file['extension']) }}"
                                        >Replace</button>
                                    @endif
                                    @if ($canDeleteFiles && $file['deletable'])
                                        <form method="POST" action="{{ route('admin.file-manager.destroy') }}" class="d-inline" onsubmit="return confirm('Delete this file?');">
                                            @csrf
                                            @method('DELETE')
                                            <input type="hidden" name="current_directory" value="{{ $listing['current_directory'] }}">
                                            <input type="hidden" name="path" value="{{ $file['path'] }}">
                                            <input type="hidden" name="entry_type" value="file">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <aside class="file-manager-panel file-manager-rail">
        <section class="mb-4">
            <h5 class="fw-semibold mb-3">Activity Chart</h5>
            <div class="file-manager-mini-chart">
                @foreach ($listing['activity_chart'] as $bar)
                    <div class="file-manager-mini-bar">
                        <div class="text-muted font-size-11 mb-2">{{ $bar['count'] }}</div>
                        <div class="file-manager-mini-bar-column bg-{{ $bar['accent'] }}" style="height: {{ $bar['height'] }}%;"></div>
                        <div class="text-muted font-size-11 mt-2">{{ $bar['label'] }}</div>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="mb-4">
            <h5 class="fw-semibold mb-3">File Types</h5>
            <div class="file-manager-type-list">
                @forelse ($listing['recent_types'] as $type)
                    <div class="file-manager-type-item">
                        <div class="d-flex align-items-center gap-3">
                            <div class="file-manager-storage-icon file-manager-accent-{{ $type['accent'] }}">
                                <i class="{{ $type['icon'] }} font-size-20"></i>
                            </div>
                            <div>
                                <div class="fw-semibold">{{ $type['label'] }}</div>
                                <div class="text-muted font-size-12">{{ $type['count'] }} files</div>
                            </div>
                        </div>
                        <div class="text-muted font-size-12">{{ $type['size_label'] }}</div>
                    </div>
                @empty
                    <div class="text-muted font-size-13">No file activity yet.</div>
                @endforelse
            </div>
        </section>

        <section class="file-manager-upload-card">
            <div class="mb-3">
                <i class="uil-import font-size-48 text-primary"></i>
            </div>
            <h5 class="mb-2">Import Files</h5>
            <p class="text-muted font-size-13 mb-3">Drop tenant assets here to make them available for items, sliders, and future content workflows.</p>

            @if ($canUploadCurrentSource)
                <form method="POST" action="{{ route('admin.file-manager.store') }}" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="current_directory" value="{{ $listing['current_directory'] }}">
                    <input type="file" name="files[]" class="form-control form-control-sm mb-3" multiple required>
                    <button type="submit" class="btn btn-primary w-100">Import Files</button>
                </form>
            @else
                @if ($listing['current_source'] === 'uploads')
                    <button type="button" class="btn btn-light w-100" disabled>Manage Permission Required</button>
                @else
                    <a href="{{ route('admin.file-manager.index') }}" class="btn btn-outline-primary w-100">Upload in My Files</a>
                    <div class="text-muted font-size-12 mt-2">Upload to My Files, then use Replace on a linked image.</div>
                @endif
            @endif
        </section>
    </aside>
</div>

@if ($canEditFiles)
    <div class="modal fade" id="renameFileModal" tabindex="-1" aria-labelledby="renameFileModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="{{ route('admin.file-manager.rename') }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="current_directory" value="{{ $listing['current_directory'] }}">
                    <input type="hidden" name="path" id="renameFilePath" value="{{ old('path') }}">
                    <div class="modal-header">
                        <h5 class="modal-title" id="renameFileModalLabel">Rename File</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <label for="renameFileName" class="form-label">File name</label>
                        <input type="text" name="new_name" id="renameFileName" class="form-control @error('new_name') is-invalid @enderror" value="{{ old('new_name') }}" maxlength="201" required>
                        <div class="form-text">The file extension must stay the same.</div>
                        @error('new_name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Name</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif

@if ($canReplaceFromMyFiles)
    <div class="modal fade" id="replaceFileModal" tabindex="-1" aria-labelledby="replaceFileModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="{{ route('admin.file-manager.replace-from-my-files') }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="current_directory" value="{{ $listing['current_directory'] }}">
                    <input type="hidden" name="target_path" id="replaceFileTargetPath" value="{{ old('target_path') }}">
                    <div class="modal-header">
                        <h5 class="modal-title" id="replaceFileModalLabel">Replace Linked Image</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-light border mb-3">
                            <div class="fw-semibold" id="replaceFileTargetName">Target image</div>
                            <div class="text-muted font-size-12" id="replaceFileTargetOwner"></div>
                        </div>
                        <label for="replaceFileSourcePath" class="form-label">Image from My Files</label>
                        <select name="source_path" id="replaceFileSourcePath" class="form-select @error('source_path') is-invalid @enderror" required>
                            <option value="">Choose an image</option>
                            @foreach ($listing['replacement_sources'] as $sourceFile)
                                <option
                                    value="{{ $sourceFile['path'] }}"
                                    data-extension="{{ strtolower($sourceFile['extension']) }}"
                                    @selected(old('source_path') === $sourceFile['path'])
                                >{{ $sourceFile['name'] }} ({{ $sourceFile['size_label'] }})</option>
                            @endforeach
                        </select>
                        @error('source_path')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        @error('target_path')
                            <div class="text-danger font-size-12 mt-2">{{ $message }}</div>
                        @enderror
                        <div class="alert alert-warning mt-3 mb-0 font-size-13">
                            The selected My Files image will replace this linked image and its staging copy will be removed.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" id="replaceFileSubmit" class="btn btn-success">Replace Image</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif
@endsection

@push('scripts')
<script src="{{ global_asset('minible/assets/libs/datatables.net/js/jquery.dataTables.min.js') }}"></script>
<script src="{{ global_asset('minible/assets/libs/datatables.net-bs4/js/dataTables.bootstrap4.min.js') }}"></script>
<script src="{{ global_asset('minible/assets/libs/datatables.net-responsive/js/dataTables.responsive.min.js') }}"></script>
<script src="{{ global_asset('minible/assets/libs/datatables.net-responsive-bs4/js/responsive.bootstrap4.min.js') }}"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const fileTable = document.getElementById('datatable-file-manager');
        const selectAllFiles = document.getElementById('selectAllFiles');
        const bulkDeleteForm = document.getElementById('bulkDeleteFilesForm');
        const bulkDeleteButton = document.getElementById('bulkDeleteFilesButton');
        const bulkDeleteCount = document.getElementById('bulkDeleteFilesCount');
        const bulkDeletePaths = document.getElementById('bulkDeleteFilePaths');
        const selectedFilePaths = new Set();
        let fileDataTable = null;

        const filteredCheckboxes = function () {
            if (!fileDataTable) {
                return Array.from(document.querySelectorAll('[data-file-select]'));
            }

            return fileDataTable
                .rows({ search: 'applied' })
                .nodes()
                .toArray()
                .map(function (row) { return row.querySelector('[data-file-select]'); })
                .filter(Boolean);
        };

        const syncFileSelection = function () {
            document.querySelectorAll('[data-file-select]').forEach(function (checkbox) {
                checkbox.checked = selectedFilePaths.has(checkbox.value);
            });

            const filtered = filteredCheckboxes();
            const selectedFilteredCount = filtered.filter(function (checkbox) {
                return selectedFilePaths.has(checkbox.value);
            }).length;

            if (selectAllFiles) {
                selectAllFiles.disabled = filtered.length === 0;
                selectAllFiles.checked = filtered.length > 0 && selectedFilteredCount === filtered.length;
                selectAllFiles.indeterminate = selectedFilteredCount > 0 && selectedFilteredCount < filtered.length;
            }

            if (bulkDeleteButton && bulkDeleteCount) {
                bulkDeleteButton.disabled = selectedFilePaths.size === 0;
                bulkDeleteCount.textContent = String(selectedFilePaths.size);
            }
        };

        if (window.jQuery && jQuery.fn.DataTable) {
            fileDataTable = jQuery('#datatable-file-manager').DataTable({
                responsive: true,
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']],
                order: [[2, 'desc']],
                columnDefs: [
                    { targets: [0, 5], orderable: false, searchable: false },
                    { targets: 0, responsivePriority: 1 },
                    { targets: 1, responsivePriority: 2 },
                    { targets: 5, responsivePriority: 3 }
                ],
                language: {
                    emptyTable: 'No files found in this location.'
                }
            });
            fileDataTable.on('draw', syncFileSelection);
            jQuery('.dataTables_length select').addClass('form-select form-select-sm');
        }

        if (fileTable) {
            fileTable.addEventListener('change', function (event) {
                const checkbox = event.target.closest('[data-file-select]');

                if (!checkbox) {
                    return;
                }

                if (checkbox.checked) {
                    selectedFilePaths.add(checkbox.value);
                } else {
                    selectedFilePaths.delete(checkbox.value);
                }

                syncFileSelection();
            });
        }

        if (selectAllFiles) {
            selectAllFiles.addEventListener('change', function () {
                filteredCheckboxes().forEach(function (checkbox) {
                    if (selectAllFiles.checked) {
                        selectedFilePaths.add(checkbox.value);
                    } else {
                        selectedFilePaths.delete(checkbox.value);
                    }
                });

                syncFileSelection();
            });
        }

        if (bulkDeleteForm && bulkDeletePaths) {
            bulkDeleteForm.addEventListener('submit', function (event) {
                if (selectedFilePaths.size === 0) {
                    event.preventDefault();
                    return;
                }

                if (!window.confirm('Delete ' + selectedFilePaths.size + ' selected file(s)? This cannot be undone.')) {
                    event.preventDefault();
                    return;
                }

                bulkDeletePaths.innerHTML = '';
                selectedFilePaths.forEach(function (path) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'paths[]';
                    input.value = path;
                    bulkDeletePaths.appendChild(input);
                });
            });
        }

        syncFileSelection();

        document.querySelectorAll('[data-copy-url]').forEach(function (button) {
            button.addEventListener('click', async function () {
                const url = button.getAttribute('data-copy-url');

                if (!url || !navigator.clipboard) {
                    window.prompt('Copy this link:', url || '');
                    return;
                }

                try {
                    await navigator.clipboard.writeText(url);
                    button.textContent = 'Copied';

                    window.setTimeout(function () {
                        button.textContent = 'Copy Link';
                    }, 1500);
                } catch (error) {
                    window.prompt('Copy this link:', url);
                }
            });
        });

        const renameModal = document.getElementById('renameFileModal');

        if (renameModal) {
            renameModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;

                if (!button) {
                    return;
                }

                document.getElementById('renameFilePath').value = button.getAttribute('data-rename-path') || '';
                document.getElementById('renameFileName').value = button.getAttribute('data-rename-name') || '';
            });

            @if ($errors->has('new_name'))
                bootstrap.Modal.getOrCreateInstance(renameModal).show();
            @endif
        }

        const replaceModal = document.getElementById('replaceFileModal');

        if (replaceModal) {
            replaceModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;

                if (!button) {
                    return;
                }

                const targetExtension = (button.getAttribute('data-replace-extension') || '').toLowerCase();
                const sourceSelect = document.getElementById('replaceFileSourcePath');
                let firstMatchingValue = '';

                document.getElementById('replaceFileTargetPath').value = button.getAttribute('data-replace-path') || '';
                document.getElementById('replaceFileTargetName').textContent = button.getAttribute('data-replace-name') || 'Target image';
                document.getElementById('replaceFileTargetOwner').textContent = button.getAttribute('data-replace-owner') || '';

                Array.from(sourceSelect.options).forEach(function (option) {
                    if (!option.value) {
                        return;
                    }

                    const matches = (option.getAttribute('data-extension') || '').toLowerCase() === targetExtension;
                    option.hidden = !matches;
                    option.disabled = !matches;

                    if (matches && !firstMatchingValue) {
                        firstMatchingValue = option.value;
                    }
                });

                sourceSelect.value = firstMatchingValue;
                document.getElementById('replaceFileSubmit').disabled = !firstMatchingValue;
            });

            @if ($errors->has('source_path') || $errors->has('target_path'))
                bootstrap.Modal.getOrCreateInstance(replaceModal).show();
            @endif
        }
    });
</script>
@endpush
