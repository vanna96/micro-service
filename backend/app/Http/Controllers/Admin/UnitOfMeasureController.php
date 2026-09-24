<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\UnitOfMeasure;
use App\Repositories\UnitOfMeasureRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UnitOfMeasureController extends Controller
{
    protected UnitOfMeasureRepository $units;

    public function __construct(UnitOfMeasureRepository $units)
    {
        $this->units = $units;
        $this->middleware('admin.permission:units_of_measure.view')->only(['index']);
        $this->middleware('admin.permission:units_of_measure.create')->only(['create', 'store']);
        $this->middleware('admin.permission:units_of_measure.edit')->only(['edit', 'update']);
        $this->middleware('admin.permission:units_of_measure.delete')->only(['destroy']);
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->get('search', ''));
        $selectedTenant = $this->requiredTenant();

        return view('admin.units-of-measure.index', [
            'units' => $this->units->getAdminListing($search),
            'search' => $search,
            'selectedTenant' => $selectedTenant,
        ]);
    }

    public function create(): View
    {
        $selectedTenant = $this->requiredTenant();

        return view('admin.units-of-measure.create', [
            'unit' => new UnitOfMeasure([
                'uom_group_id' => null,
                'alternate_quantity' => 1,
                'base_quantity' => 1,
                'conversion_factor_to_base' => 1,
                'decimal_places' => 2,
                'sort_order' => 0,
                'status' => 'Active',
            ]),
            'selectedTenant' => $selectedTenant,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->requiredTenant();
        $validated = $this->validateUnit($request);
        $this->units->createForAdmin($validated);

        return redirect()
            ->route('admin.units-of-measure.index')
            ->with('status', 'Unit of measure created successfully.');
    }

    public function edit(string $units_of_measure): View
    {
        $selectedTenant = $this->requiredTenant();
        $unit = $this->units->loadForAdminEdit((int) $units_of_measure);

        return view('admin.units-of-measure.edit', [
            'unit' => $unit,
            'selectedTenant' => $selectedTenant,
        ]);
    }

    public function update(Request $request, string $units_of_measure): RedirectResponse
    {
        $this->requiredTenant();
        $unit = $this->units->loadForAdminEdit((int) $units_of_measure);
        $validated = $this->validateUnit($request, $unit);
        $this->units->updateForAdmin($unit, $validated);

        return redirect()
            ->route('admin.units-of-measure.index')
            ->with('status', 'Unit of measure updated successfully.');
    }

    public function destroy(string $units_of_measure): RedirectResponse
    {
        $this->requiredTenant();
        $unit = $this->units->loadForAdminEdit((int) $units_of_measure);
        $this->units->deleteForAdmin($unit);

        return redirect()
            ->route('admin.units-of-measure.index')
            ->with('status', 'Unit of measure deleted successfully.');
    }

    private function validateUnit(Request $request, ?UnitOfMeasure $unit = null): array
    {
        $unitId = $unit?->id;
        $unitTable = $this->unitValidationTable();

        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:64',
                Rule::unique($unitTable, 'code')
                    ->where(fn ($query) => $query->whereNull('uom_group_id'))
                    ->ignore($unitId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'foreign_name' => ['nullable', 'string', 'max:255'],
            'symbol' => ['nullable', 'string', 'max:32'],
            'alternate_quantity' => ['nullable', 'numeric', 'gt:0'],
            'base_quantity' => ['nullable', 'numeric', 'gt:0'],
            'is_base_unit' => ['nullable', 'boolean'],
            'decimal_places' => ['nullable', 'integer', 'min:0', 'max:6'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
        ]);

        $validated['uom_group_id'] = null;
        $validated['alternate_quantity'] ??= $unit?->alternate_quantity ?? 1;
        $validated['base_quantity'] ??= $unit?->base_quantity ?? 1;
        $validated['decimal_places'] ??= $unit?->decimal_places ?? 2;
        $validated['sort_order'] ??= $unit?->sort_order ?? 0;
        $validated['is_base_unit'] ??= $unit?->is_base_unit ?? false;

        return $validated;
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
