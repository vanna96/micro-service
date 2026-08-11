<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\UnitOfMeasure;
use App\Models\UomGroup;
use App\Repositories\UnitOfMeasureRepository;
use App\Repositories\UomGroupRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UomGroupController extends Controller
{
    protected UomGroupRepository $groups;
    protected UnitOfMeasureRepository $units;

    public function __construct(UomGroupRepository $groups, UnitOfMeasureRepository $units)
    {
        $this->groups = $groups;
        $this->units = $units;
        $this->middleware('admin.permission:units_of_measure.view')->only(['index']);
        $this->middleware('admin.permission:units_of_measure.manage')->except(['index']);
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->get('search', ''));
        $selectedTenant = $this->requiredTenant();

        return view('admin.uom-groups.index', [
            'groups' => $this->groups->getAdminListing($search),
            'search' => $search,
            'selectedTenant' => $selectedTenant,
        ]);
    }

    public function create(): View
    {
        $selectedTenant = $this->requiredTenant();

        return view('admin.uom-groups.create', [
            'group' => new UomGroup([
                'status' => 'Active',
            ]),
            'unitOptions' => $this->units->getOptions(),
            'selectedTenant' => $selectedTenant,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->requiredTenant();
        $validated = $this->validateGroup($request);
        $group = $this->groups->createForAdmin($validated);

        return redirect()
            ->route('admin.uom-groups.edit', ['uom_group' => $group->id])
            ->with('status', 'UoM group created successfully.');
    }

    public function edit(string $uom_group): View
    {
        $selectedTenant = $this->requiredTenant();

        $group = $this->groups->loadForAdminEdit((int) $uom_group);

        return view('admin.uom-groups.edit', [
            'group' => $group,
            'unitOptions' => $this->units->getOptions($group->units->pluck('unit.code')->all()),
            'selectedTenant' => $selectedTenant,
        ]);
    }

    public function update(Request $request, string $uom_group): RedirectResponse
    {
        $this->requiredTenant();
        $group = $this->groups->loadForAdminEdit((int) $uom_group);
        $validated = $this->validateGroup($request, $group);
        $this->groups->updateForAdmin($group, $validated);

        return redirect()
            ->route('admin.uom-groups.edit', ['uom_group' => $group->id])
            ->with('status', 'UoM group updated successfully.');
    }

    public function destroy(string $uom_group): RedirectResponse
    {
        $this->requiredTenant();
        $group = $this->groups->loadForAdminEdit((int) $uom_group);
        $this->groups->deleteForAdmin($group);

        return redirect()
            ->route('admin.uom-groups.index')
            ->with('status', 'UoM group deleted successfully.');
    }

    private function validateGroup(Request $request, ?UomGroup $group = null): array
    {
        $groupId = $group?->id;
        $table = $this->groupValidationTable();
        $unitTable = $this->unitValidationTable();

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:64', Rule::unique($table, 'code')->ignore($groupId)],
            'name' => ['required', 'string', 'max:255'],
            'foreign_name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
            'base_unit_row' => ['nullable', 'string'],
            'units' => ['nullable', 'array'],
            'units.*.id' => ['nullable', 'integer'],
            'units.*.unit_of_measure_id' => [
                'nullable',
                'integer',
                Rule::exists($unitTable, 'id')->where(fn ($query) => $query->whereNull('uom_group_id')),
            ],
            'units.*.alternate_quantity' => ['nullable', 'numeric', 'gt:0'],
            'units.*.base_quantity' => ['nullable', 'numeric', 'gt:0'],
            'units.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'units.*.status' => ['nullable', Rule::in(['Active', 'Inactive'])],
            'units.*._delete' => ['nullable', 'boolean'],
        ]);

        $validated['units'] = collect($validated['units'] ?? [])
            ->filter(function (array $unit) {
                if ((bool) ($unit['_delete'] ?? false)) {
                    return true;
                }

                return filled($unit['unit_of_measure_id'] ?? null);
            })
            ->map(function (array $unit, string|int $rowKey) use ($validated) {
                $isDeleted = (bool) ($unit['_delete'] ?? false);
                $isBaseUnit = ! $isDeleted && (string) ($validated['base_unit_row'] ?? '') === (string) $rowKey;

                return [
                    'id' => $unit['id'] ?? null,
                    'unit_of_measure_id' => $unit['unit_of_measure_id'] ?? null,
                    'alternate_quantity' => $isBaseUnit ? 1 : ($unit['alternate_quantity'] ?? 1),
                    'base_quantity' => $isBaseUnit ? 1 : ($unit['base_quantity'] ?? 1),
                    'sort_order' => $unit['sort_order'] ?? 0,
                    'status' => $unit['status'] ?? 'Active',
                    'is_base_unit' => $isBaseUnit,
                    '_delete' => $isDeleted,
                ];
            })
            ->values()
            ->all();

        $activeUnits = collect($validated['units'])->reject(fn (array $unit) => $unit['_delete'] ?? false);

        if ($activeUnits->isNotEmpty()) {
            $activeUnits->each(function (array $unit, int $index) {
                validator($unit, [
                    'unit_of_measure_id' => ['required', 'integer'],
                    'alternate_quantity' => ['required', 'numeric', 'gt:0'],
                    'base_quantity' => ['required', 'numeric', 'gt:0'],
                ], [], [
                    'unit_of_measure_id' => "unit row " . ($index + 1) . " UoM",
                    'alternate_quantity' => "unit row " . ($index + 1) . " alternate quantity",
                    'base_quantity' => "unit row " . ($index + 1) . " base quantity",
                ])->validate();
            });

            validator([
                'unit_ids' => $activeUnits->pluck('unit_of_measure_id')->all(),
                'base_units' => $activeUnits->where('is_base_unit', true)->count(),
            ], [
                'unit_ids.*' => ['distinct'],
                'base_units' => ['required', 'integer', 'min:1', 'max:1'],
            ], [
                'unit_ids.*.distinct' => 'Each UoM can appear only once inside the UoM group.',
                'base_units.min' => 'Select exactly one base unit in the group definition.',
                'base_units.max' => 'Select exactly one base unit in the group definition.',
            ])->validate();
        }

        unset($validated['base_unit_row']);

        return $validated;
    }

    private function groupValidationTable(): string
    {
        $table = (new UomGroup())->getTable();

        if (tenant()) {
            return tenant()->database_connection_name . '.' . $table;
        }

        return 'central.' . $table;
    }

    private function unitValidationTable(): string
    {
        $table = (new UnitOfMeasure())->getTable();

        if (tenant()) {
            return tenant()->database_connection_name . '.' . $table;
        }

        return 'central.' . $table;
    }

    private function requiredTenant(): Tenant
    {
        $tenant = admin_current_tenant();

        abort_if(! $tenant, 404);

        return $tenant;
    }
}
