@php
    $checkedPermissionIds = collect(old('permissions', $selectedPermissions))
        ->map(fn ($id) => (int) $id)
        ->all();

    $moduleLabels = [
        'activity_logs' => 'Activity Logs',
        'addresses' => 'Addresses',
        'branches' => 'Branches',
        'categories' => 'Categories',
        'currencies' => 'Currencies',
        'customers' => 'Customers',
        'file_manager' => 'File Manager',
        'general_settings' => 'General Settings',
        'item_options' => 'Item Options',
        'item_variations' => 'Item Variations',
        'items' => 'Items & Catalog',
        'pos' => 'POS & Orders',
        'price_lists' => 'Price Lists',
        'promotions' => 'Promotions',
        'rate_indexes' => 'Exchange Rates',
        'reports' => 'Reports',
        'roles' => 'Roles & Permissions',
        'sliders' => 'Sliders',
        'tenant_users' => 'Tenant Users',
        'units_of_measure' => 'Units of Measure',
        'uom_groups' => 'UOM Groups',
    ];

    $actionLabels = [
        'view' => 'View',
        'create' => 'Create',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'export' => 'Export',
    ];

    $actionOrder = array_flip(array_keys($actionLabels));

    $totalSystemPermissions = 0;
    foreach ($permissionGroups as $grpPerms) {
        $totalSystemPermissions += $grpPerms->count();
    }
@endphp

@once
    @push('styles')
        <style>
            .role-sticky-panel {
                position: -webkit-sticky;
                position: sticky;
                top: 80px;
                z-index: 10;
            }

            .permission-row {
                padding: 12px 16px;
                border-radius: 10px;
                border: 1px solid transparent;
                transition: background-color 0.15s ease, border-color 0.15s ease;
            }

            .permission-row:hover {
                background-color: #f8fafc;
                border-color: #f1f5f9;
            }

            .permission-search-wrap {
                position: relative;
                max-width: 300px;
            }

            .permission-search-wrap i {
                position: absolute;
                left: 12px;
                top: 50%;
                transform: translateY(-50%);
                color: #94a3b8;
                font-size: 15px;
                pointer-events: none;
            }

            .permission-search-wrap input {
                padding-left: 34px;
                height: 36px;
                border-radius: 8px;
                font-size: 13px;
                border: 1px solid #e2e8f0;
            }

            .permission-search-wrap input:focus {
                border-color: #5b73e8;
                box-shadow: 0 0 0 2px rgba(91, 115, 232, 0.15);
            }

            .form-check-input {
                cursor: pointer;
            }

            .form-check-label {
                cursor: pointer;
                user-select: none;
            }
        </style>
    @endpush
@endonce

<div class="row g-4">
    <!-- Left Column: Role Details -->
    <div class="col-lg-4">
        <div class="role-sticky-panel">
            <div class="card border shadow-sm rounded-4 mb-3 bg-white">
                <div class="card-header bg-white border-bottom px-4 py-3">
                    <h5 class="card-title mb-0 fw-bold text-dark font-size-15">Role Details</h5>
                </div>
                <div class="card-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold font-size-13 text-dark">
                            Role Display Name <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="label" value="{{ old('label', $role->label) }}"
                            class="form-control @error('label') is-invalid @enderror"
                            placeholder="e.g. Store Manager" required />
                        @error('label')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold font-size-13 text-dark">
                            Role Key / Slug <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="name" value="{{ old('name', $role->name) }}"
                            class="form-control font-monospace font-size-13 @error('name') is-invalid @enderror"
                            placeholder="e.g. store-manager" required />
                        <div class="form-text font-size-11 text-muted">Unique key (lowercase, hyphens).</div>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold font-size-13 text-dark">Description</label>
                        <textarea name="description" rows="3"
                            class="form-control font-size-13 @error('description') is-invalid @enderror"
                            placeholder="Optional notes about what this role is for...">{{ old('description', $role->description) }}</textarea>
                        @error('description')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <hr class="my-3" />

                    <!-- Minimalist Permission Counter -->
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted font-size-12 fw-medium">Permissions Granted</span>
                        <span class="fw-bold font-size-12 text-primary" id="global-counter">
                            0 / {{ $totalSystemPermissions }}
                        </span>
                    </div>
                    <div class="progress mb-3" style="height: 5px; border-radius: 999px;">
                        <div class="progress-bar bg-primary" id="global-progress" role="progressbar" style="width: 0%;"></div>
                    </div>
                    <div class="d-flex justify-content-between">
                        <button type="button" class="btn btn-sm btn-link text-primary p-0 text-decoration-none font-size-12" id="btn-global-select-all">
                            Select all
                        </button>
                        <button type="button" class="btn btn-sm btn-link text-muted p-0 text-decoration-none font-size-12" id="btn-global-clear-all">
                            Clear all
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column: Clean Minimalist Permissions Matrix -->
    <div class="col-lg-8">
        <div class="card border shadow-sm rounded-4 bg-white overflow-hidden mb-4">
            <div class="card-header bg-white border-bottom px-4 py-3 d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2">
                <div>
                    <h5 class="card-title mb-0 fw-bold text-dark font-size-15">Permissions Matrix</h5>
                    <span class="text-muted font-size-12">Assign granular capabilities per module</span>
                </div>
                <div class="permission-search-wrap">
                    <i class="uil uil-search"></i>
                    <input type="text" id="permission-filter-input" class="form-control" placeholder="Filter permissions..." autocomplete="off" />
                </div>
            </div>

            <div class="card-body p-4">
                @error('permissions')
                    <div class="alert alert-danger alert-border-left rounded-3 mb-3 font-size-13">{{ $message }}</div>
                @enderror
                @error('permissions.*')
                    <div class="alert alert-danger alert-border-left rounded-3 mb-3 font-size-13">{{ $message }}</div>
                @enderror

                <div id="no-search-results" class="text-center py-4 text-muted font-size-13 d-none">
                    No permissions match your search filter.
                </div>

                <div id="permission-groups-list">
                    @foreach ($permissionGroups as $groupName => $permissions)
                        @php
                            $modulePermissions = $permissions
                                ->groupBy(fn ($permission) => \Illuminate\Support\Str::beforeLast($permission->name, '.'))
                                ->sortKeys();
                        @endphp
                        <div class="permission-group-block mb-4" data-group-block>
                            <!-- Group Title -->
                            <div class="d-flex align-items-center justify-content-between pb-2 mb-2 border-bottom">
                                <span class="text-uppercase fw-bold text-muted font-size-11 letter-spacing-1">
                                    {{ $groupName }}
                                </span>
                                <button type="button" class="btn btn-sm btn-link text-muted p-0 text-decoration-none font-size-11 group-select-all-btn">
                                    Select group
                                </button>
                            </div>

                            <!-- Modules in this group -->
                            <div class="d-flex flex-column gap-1">
                                @foreach ($modulePermissions as $moduleName => $permissionsForModule)
                                    @php
                                        $sortedPermissions = $permissionsForModule->sortBy(function ($permission) use ($actionOrder) {
                                            $action = \Illuminate\Support\Str::afterLast($permission->name, '.');
                                            return $actionOrder[$action] ?? 99;
                                        });
                                        $moduleSelectedCount = $sortedPermissions
                                            ->filter(fn ($permission) => in_array((int) $permission->id, $checkedPermissionIds, true))
                                            ->count();
                                        $moduleTotal = $sortedPermissions->count();
                                        $moduleLabel = $moduleLabels[$moduleName] ?? \Illuminate\Support\Str::headline($moduleName);
                                    @endphp
                                    <div class="permission-row d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2"
                                        data-permission-module
                                        data-module-search="{{ strtolower($moduleLabel . ' ' . $moduleName . ' ' . $groupName) }}">
                                        <!-- Module Name & Select All -->
                                        <div class="d-flex align-items-center gap-2" style="min-width: 220px;">
                                            <div class="form-check m-0">
                                                <input class="form-check-input permission-select-all" type="checkbox"
                                                    id="mod-{{ $moduleName }}"
                                                    @checked($moduleSelectedCount === $moduleTotal)
                                                    title="Select all for this module">
                                            </div>
                                            <label class="form-check-label m-0" for="mod-{{ $moduleName }}">
                                                <div class="fw-semibold font-size-13 text-dark">{{ $moduleLabel }}</div>
                                                <code class="text-muted font-size-10">{{ $moduleName }}</code>
                                            </label>
                                        </div>

                                        <!-- Action Checkboxes Inline -->
                                        <div class="d-flex align-items-center flex-wrap gap-3">
                                            @foreach ($sortedPermissions as $permission)
                                                @php
                                                    $action = \Illuminate\Support\Str::afterLast($permission->name, '.');
                                                    $isChecked = in_array((int) $permission->id, $checkedPermissionIds, true);
                                                @endphp
                                                <div class="form-check m-0" data-permission-item data-item-search="{{ strtolower($action . ' ' . $permission->name) }}">
                                                    <input class="form-check-input permission-action-checkbox"
                                                        type="checkbox"
                                                        name="permissions[]"
                                                        value="{{ $permission->id }}"
                                                        id="perm-{{ $permission->id }}"
                                                        @checked($isChecked)>
                                                    <label class="form-check-label font-size-12 text-dark" for="perm-{{ $permission->id }}">
                                                        {{ $actionLabels[$action] ?? ucfirst($action) }}
                                                    </label>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

@once
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var allModules = Array.from(document.querySelectorAll('[data-permission-module]'));
                var allGroupBlocks = Array.from(document.querySelectorAll('[data-group-block]'));
                var searchInput = document.getElementById('permission-filter-input');
                var noResultsMsg = document.getElementById('no-search-results');
                var globalCounter = document.getElementById('global-counter');
                var globalProgress = document.getElementById('global-progress');
                var totalSystem = {{ (int) $totalSystemPermissions }};

                function updateStats() {
                    var totalChecked = document.querySelectorAll('.permission-action-checkbox:checked').length;
                    if (globalCounter) {
                        globalCounter.textContent = totalChecked + ' / ' + totalSystem;
                    }
                    if (globalProgress) {
                        var percent = totalSystem > 0 ? Math.round((totalChecked / totalSystem) * 100) : 0;
                        globalProgress.style.width = percent + '%';
                    }
                }

                allModules.forEach(function (module) {
                    var checkboxes = Array.from(module.querySelectorAll('.permission-action-checkbox'));
                    var selectAll = module.querySelector('.permission-select-all');

                    function syncModule() {
                        var selectedCount = checkboxes.filter(function (cb) { return cb.checked; }).length;
                        if (selectAll) {
                            selectAll.checked = (selectedCount === checkboxes.length);
                            selectAll.indeterminate = (selectedCount > 0 && selectedCount < checkboxes.length);
                        }
                        updateStats();
                    }

                    if (selectAll) {
                        selectAll.addEventListener('change', function () {
                            checkboxes.forEach(function (cb) { cb.checked = selectAll.checked; });
                            syncModule();
                        });
                    }

                    checkboxes.forEach(function (cb) {
                        cb.addEventListener('change', syncModule);
                    });

                    syncModule();
                });

                // Group toggle buttons
                allGroupBlocks.forEach(function (block) {
                    var btn = block.querySelector('.group-select-all-btn');
                    if (!btn) return;
                    btn.addEventListener('click', function () {
                        var checkboxes = Array.from(block.querySelectorAll('.permission-action-checkbox'));
                        var allChecked = checkboxes.every(function (cb) { return cb.checked; });
                        checkboxes.forEach(function (cb) { cb.checked = !allChecked; });
                        allModules.forEach(function (mod) {
                            var modSelectAll = mod.querySelector('.permission-select-all');
                            var modCheckboxes = Array.from(mod.querySelectorAll('.permission-action-checkbox'));
                            var modCount = modCheckboxes.filter(function (c) { return c.checked; }).length;
                            if (modSelectAll) {
                                modSelectAll.checked = (modCount === modCheckboxes.length);
                                modSelectAll.indeterminate = (modCount > 0 && modCount < modCheckboxes.length);
                            }
                        });
                        updateStats();
                    });
                });

                // Global Select All / Clear All
                var btnGlobalAll = document.getElementById('btn-global-select-all');
                var btnGlobalClear = document.getElementById('btn-global-clear-all');

                if (btnGlobalAll) {
                    btnGlobalAll.addEventListener('click', function () {
                        document.querySelectorAll('.permission-action-checkbox').forEach(function (cb) {
                            cb.checked = true;
                        });
                        document.querySelectorAll('.permission-select-all').forEach(function (cb) {
                            cb.checked = true;
                            cb.indeterminate = false;
                        });
                        updateStats();
                    });
                }

                if (btnGlobalClear) {
                    btnGlobalClear.addEventListener('click', function () {
                        document.querySelectorAll('.permission-action-checkbox').forEach(function (cb) {
                            cb.checked = false;
                        });
                        document.querySelectorAll('.permission-select-all').forEach(function (cb) {
                            cb.checked = false;
                            cb.indeterminate = false;
                        });
                        updateStats();
                    });
                }

                // Filter search
                if (searchInput) {
                    searchInput.addEventListener('input', function () {
                        var q = this.value.trim().toLowerCase();
                        var totalVisible = 0;

                        allGroupBlocks.forEach(function (block) {
                            var modules = Array.from(block.querySelectorAll('[data-permission-module]'));
                            var groupMatched = 0;

                            modules.forEach(function (mod) {
                                var modText = mod.getAttribute('data-module-search') || '';
                                var items = Array.from(mod.querySelectorAll('[data-permission-item]'));
                                var itemMatches = 0;

                                items.forEach(function (item) {
                                    var itemText = item.getAttribute('data-item-search') || '';
                                    var matches = (q === '' || itemText.indexOf(q) !== -1 || modText.indexOf(q) !== -1);
                                    item.style.display = matches ? '' : 'none';
                                    if (matches) itemMatches++;
                                });

                                var showMod = (q === '' || modText.indexOf(q) !== -1 || itemMatches > 0);
                                mod.style.display = showMod ? '' : 'none';
                                if (showMod) {
                                    groupMatched++;
                                    totalVisible++;
                                }
                            });

                            block.style.display = groupMatched > 0 ? '' : 'none';
                        });

                        if (noResultsMsg) {
                            noResultsMsg.classList.toggle('d-none', totalVisible > 0);
                        }
                    });
                }

                updateStats();
            });
        </script>
    @endpush
@endonce
