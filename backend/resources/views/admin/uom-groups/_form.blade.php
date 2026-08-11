@php
    $unitOptions = collect($unitOptions ?? []);
    $oldUnitRows = old('units');

    if (is_array($oldUnitRows)) {
        $unitRows = collect($oldUnitRows);
    } elseif ($group->relationLoaded('units')) {
        $unitRows = collect();

        foreach ($group->units as $line) {
            $unitRows->put('existing_' . $line->id, [
                'id' => $line->id,
                'unit_of_measure_id' => $line->unit_of_measure_id,
                'code' => $line->unit?->code,
                'name' => $line->unit?->name,
                'alternate_quantity' => $line->alternate_quantity,
                'base_quantity' => $line->base_quantity,
                'sort_order' => $line->sort_order,
                'status' => $line->status,
                'is_base_unit' => $line->is_base_unit,
            ]);
        }
    } else {
        $unitRows = collect();
    }

    if ($group->exists && $unitRows->isEmpty()) {
        $unitRows = collect([
            'new_0' => [
                'id' => null,
                'unit_of_measure_id' => null,
                'alternate_quantity' => 1,
                'base_quantity' => 1,
                'sort_order' => 0,
                'status' => 'Active',
                'is_base_unit' => true,
            ],
        ]);
    }

    $firstUnitRowKey = (string) $unitRows->keys()->first();
    $defaultBaseUnitRow = $firstUnitRowKey;

    foreach ($unitRows as $rowKey => $row) {
        if ((bool) ($row['is_base_unit'] ?? false)) {
            $defaultBaseUnitRow = (string) $rowKey;
            break;
        }
    }

    $baseUnitRow = (string) old('base_unit_row', $defaultBaseUnitRow);
    $unitOptionsJson = $unitOptions
        ->map(function ($unit) {
            return [
                'id' => $unit->id,
                'code' => $unit->code,
                'name' => $unit->name,
            ];
        })
        ->values()
        ->toJson();
@endphp

<div class="row g-3">
    <div class="col-xl-8">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">UOM Group</h4>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label required">Code</label>
                        <input type="text" name="code" value="{{ old('code', $group->code) }}" class="form-control @error('code') is-invalid @enderror" placeholder="PACK" />
                        @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label required">Name</label>
                        <input type="text" name="name" value="{{ old('name', $group->name) }}" class="form-control @error('name') is-invalid @enderror" placeholder="Packing Units" />
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Foreign Name</label>
                        <input type="text" name="foreign_name" value="{{ old('foreign_name', $group->foreign_name) }}" class="form-control @error('foreign_name') is-invalid @enderror" />
                        @error('foreign_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Description</label>
                        <textarea name="description" rows="3" class="form-control @error('description') is-invalid @enderror" placeholder="Optional note">{{ old('description', $group->description) }}</textarea>
                        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label required">Status</label>
                        <select name="status" class="form-select @error('status') is-invalid @enderror">
                            @foreach (['Active', 'Inactive'] as $status)
                                <option value="{{ $status }}" @selected(old('status', $group->status ?: 'Active') === $status)>{{ $status }}</option>
                            @endforeach
                        </select>
                        @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@if ($group->exists)
    <div class="row g-3">
        <div class="col-xl-10">
            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <div>
                        <h4 class="card-title mb-1">Group Definition</h4>
                        <p class="card-title-desc mb-0">
                            Select UoMs and define conversion like SAP: Alt. Qty / Alt. UoM = Base Qty / Base UoM.
                        </p>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" id="uom-add-row">
                        <i class="uil uil-plus me-1"></i>Add Row
                    </button>
                </div>
                <div class="card-body">
                    @if ($errors->has('base_units') || $errors->has('unit_ids.*'))
                        <div class="alert alert-danger">
                            {{ $errors->first('base_units') ?: $errors->first('unit_ids.*') }}
                        </div>
                    @endif

                    @if ($unitOptions->isEmpty())
                        <div class="alert alert-warning">
                            Create Unit of Measure records first, then return here to define this group.
                        </div>
                    @endif

                    <input type="hidden" name="base_unit_row" id="uom-base-row" value="{{ $baseUnitRow }}">

                    <div class="table-responsive">
                        <table class="table table-bordered align-middle mb-0" id="uom-definition-lines">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 48px;">#</th>
                                    <th style="width: 120px;">Alt. Qty</th>
                                    <th style="min-width: 180px;">Alt. UoM</th>
                                    <th style="width: 40px;">=</th>
                                    <th style="width: 120px;">Base Qty</th>
                                    <th style="min-width: 160px;">Base UoM</th>
                                    <th style="width: 90px;">Active</th>
                                    <th style="width: 80px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($unitRows as $rowKey => $unitRow)
                                    @php($rowKey = (string) $rowKey)
                                    <tr data-uom-row data-row-key="{{ $rowKey }}">
                                        <td class="text-center" data-row-number>{{ $loop->iteration }}</td>
                                        <td>
                                            <input type="hidden" name="units[{{ $rowKey }}][id]" value="{{ $unitRow['id'] ?? '' }}">
                                            <input type="hidden" name="units[{{ $rowKey }}][_delete]" value="0" data-delete-flag>
                                            <input type="number" name="units[{{ $rowKey }}][alternate_quantity]" value="{{ $unitRow['alternate_quantity'] ?? 1 }}" min="0.000001" step="0.000001" class="form-control text-end" data-alt-qty>
                                        </td>
                                        <td>
                                            <select name="units[{{ $rowKey }}][unit_of_measure_id]" class="form-select" data-uom-code>
                                                <option value="">Select UoM</option>
                                                @foreach ($unitOptions as $unitOption)
                                                    <option value="{{ $unitOption->id }}" data-code="{{ $unitOption->code }}" @selected((string) ($unitRow['unit_of_measure_id'] ?? '') === (string) $unitOption->id)>
                                                        {{ $unitOption->code }} - {{ $unitOption->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="text-center fw-bold">=</td>
                                        <td>
                                            <input type="number" name="units[{{ $rowKey }}][base_quantity]" value="{{ $unitRow['base_quantity'] ?? 1 }}" min="0.000001" step="0.000001" class="form-control text-end" data-base-qty>
                                        </td>
                                        <td>
                                            <input type="text" class="form-control" data-base-code readonly>
                                        </td>
                                        <td class="text-center">
                                            <input type="hidden" name="units[{{ $rowKey }}][status]" value="Inactive">
                                            <input type="checkbox" name="units[{{ $rowKey }}][status]" value="Active" class="form-check-input" @checked(($unitRow['status'] ?? 'Active') === 'Active')>
                                        </td>
                                        <td class="text-center">
                                            <input type="hidden" name="units[{{ $rowKey }}][sort_order]" value="{{ $unitRow['sort_order'] ?? $loop->index }}">
                                            <button type="button" class="btn btn-sm btn-outline-danger" data-remove-row>Delete</button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="form-text mt-2">
                        The first visible row is the base UoM. Example: 1 Rim = 500 Sheet.
                    </div>
                </div>
            </div>
        </div>
    </div>
@else
    <div class="row g-3">
        <div class="col-xl-8">
            <div class="alert alert-info">
                Create the UOM Group first. After save, you can define the conversion rows.
            </div>
        </div>
    </div>
@endif

@if ($group->exists)
    @push('scripts')
    <script>
        (function () {
            const tableBody = document.querySelector('#uom-definition-lines tbody');
            const addButton = document.getElementById('uom-add-row');
            const baseRowInput = document.getElementById('uom-base-row');
            const unitOptions = {!! $unitOptionsJson !!};

            function escapeHtml(value) {
                return String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function renumberRows() {
                tableBody.querySelectorAll('tr[data-uom-row]:not(.d-none)').forEach((row, index) => {
                    row.querySelector('[data-row-number]').textContent = index + 1;
                    const sortOrder = row.querySelector('input[name$="[sort_order]"]');

                    if (sortOrder) {
                        sortOrder.value = index;
                    }
                });
            }

            function refreshRows() {
                const baseRow = tableBody.querySelector('tr[data-uom-row]:not(.d-none)');
                const baseSelect = baseRow?.querySelector('[data-uom-code]');
                const baseCode = baseSelect?.selectedOptions?.[0]?.dataset?.code || '';
                const baseRowKey = baseRow?.getAttribute('data-row-key') || '';

                if (baseRowInput) {
                    baseRowInput.value = baseRowKey;
                }

                tableBody.querySelectorAll('tr[data-uom-row]').forEach(row => {
                    const isDeleted = row.classList.contains('d-none');
                    const isBase = row === baseRow && !isDeleted;
                    const altQty = row.querySelector('[data-alt-qty]');
                    const baseQty = row.querySelector('[data-base-qty]');
                    const baseCodeInput = row.querySelector('[data-base-code]');
                    const codeInput = row.querySelector('[data-uom-code]');
                    const selectedOption = codeInput?.selectedOptions?.[0];
                    const selectedCode = selectedOption?.dataset?.code || '';

                    if (isBase) {
                        altQty.value = '1';
                        baseQty.value = '1';
                        altQty.readOnly = true;
                        baseQty.readOnly = true;
                        baseCodeInput.value = selectedCode;
                        row.classList.add('table-primary');
                    } else {
                        altQty.readOnly = false;
                        baseQty.readOnly = false;
                        baseCodeInput.value = baseCode;
                        row.classList.remove('table-primary');
                    }
                });

                renumberRows();
            }

            function bindRow(row) {
                row.querySelectorAll('[data-uom-code], [data-alt-qty], [data-base-qty]').forEach(input => {
                    input.addEventListener('input', refreshRows);
                    input.addEventListener('change', refreshRows);
                });

                row.querySelector('[data-remove-row]').addEventListener('click', function () {
                    const visibleRows = tableBody.querySelectorAll('tr[data-uom-row]:not(.d-none)');

                    if (visibleRows.length === 1) {
                        row.querySelectorAll('select, input[type="text"], input[type="number"]').forEach(input => {
                            if (!input.readOnly) {
                                input.value = '';
                            }
                        });
                        row.querySelector('[data-delete-flag]').value = '0';
                        refreshRows();
                        return;
                    }

                    row.querySelector('[data-delete-flag]').value = '1';
                    row.classList.add('d-none');
                    refreshRows();
                });
            }

            function addRow() {
                const key = 'new_' + Date.now();
                const row = document.createElement('tr');
                const optionsHtml = unitOptions
                    .map(option => `<option value="${escapeHtml(option.id)}" data-code="${escapeHtml(option.code)}">${escapeHtml(option.code)} - ${escapeHtml(option.name)}</option>`)
                    .join('');

                row.setAttribute('data-uom-row', '');
                row.setAttribute('data-row-key', key);
                row.innerHTML = `
                    <td class="text-center" data-row-number></td>
                    <td>
                        <input type="hidden" name="units[${key}][id]" value="">
                        <input type="hidden" name="units[${key}][_delete]" value="0" data-delete-flag>
                        <input type="number" name="units[${key}][alternate_quantity]" value="1" min="0.000001" step="0.000001" class="form-control text-end" data-alt-qty>
                    </td>
                    <td>
                        <select name="units[${key}][unit_of_measure_id]" class="form-select" data-uom-code>
                            <option value="">Select UoM</option>
                            ${optionsHtml}
                        </select>
                    </td>
                    <td class="text-center fw-bold">=</td>
                    <td><input type="number" name="units[${key}][base_quantity]" value="1" min="0.000001" step="0.000001" class="form-control text-end" data-base-qty></td>
                    <td><input type="text" class="form-control" data-base-code readonly></td>
                    <td class="text-center">
                        <input type="hidden" name="units[${key}][status]" value="Inactive">
                        <input type="checkbox" name="units[${key}][status]" value="Active" class="form-check-input" checked>
                    </td>
                    <td class="text-center">
                        <input type="hidden" name="units[${key}][sort_order]" value="0">
                        <button type="button" class="btn btn-sm btn-outline-danger" data-remove-row>Delete</button>
                    </td>
                `;

                tableBody.appendChild(row);
                bindRow(row);
                refreshRows();
                row.querySelector('[data-uom-code]').focus();
            }

            tableBody.querySelectorAll('tr[data-uom-row]').forEach(bindRow);
            addButton.addEventListener('click', addRow);
            refreshRows();
        })();
    </script>
    @endpush
@endif
