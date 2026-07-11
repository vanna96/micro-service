<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Tenant;
use App\Repositories\BranchRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BranchController extends Controller
{
    protected BranchRepository $branches;

    public function __construct(BranchRepository $branches)
    {
        $this->branches = $branches;
        $this->middleware('admin.permission:branches.view')->only(['index']);
        $this->middleware('admin.permission:branches.manage')->except(['index']);
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->get('search', ''));
        $selectedTenant = $this->requiredTenant($request);

        return view('admin.branches.index', [
            'branches' => $this->branches->getAdminListing($search),
            'search' => $search,
            'selectedTenant' => $selectedTenant,
        ]);
    }

    public function create(Request $request): View
    {
        $selectedTenant = $this->requiredTenant($request);

        return view('admin.branches.create', [
            'branch' => new Branch([
                'status' => 'Active',
            ]),
            'selectedTenant' => $selectedTenant,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->requiredTenant($request);
        $validated = $this->validateBranch($request);
        $this->branches->createForAdmin($validated);

        return redirect()
            ->route('admin.branches.index')
            ->with('status', 'Branch created successfully.');
    }

    public function edit(Request $request, string $branch): View
    {
        $selectedTenant = $this->requiredTenant($request);

        return view('admin.branches.edit', [
            'branch' => $this->branches->loadForAdminEdit((int) $branch),
            'selectedTenant' => $selectedTenant,
        ]);
    }

    public function update(Request $request, string $branch): RedirectResponse
    {
        $this->requiredTenant($request);
        $branchModel = $this->branches->loadForAdminEdit((int) $branch);
        $validated = $this->validateBranch($request, $branchModel);
        $this->branches->updateForAdmin($branchModel, $validated);

        return redirect()
            ->route('admin.branches.index')
            ->with('status', 'Branch updated successfully.');
    }

    public function destroy(Request $request, string $branch): RedirectResponse
    {
        $this->requiredTenant($request);
        $branchModel = $this->branches->loadForAdminEdit((int) $branch);
        $this->branches->deleteForAdmin($branchModel);

        return redirect()
            ->route('admin.branches.index')
            ->with('status', 'Branch deleted successfully.');
    }

    private function validateBranch(Request $request, ?Branch $branch = null): array
    {
        $branchId = $branch?->id;
        $branchTable = $this->branchValidationTable();

        return $request->validate([
            'code' => ['required', 'string', 'max:64', Rule::unique($branchTable, 'code')->ignore($branchId)],
            'name' => ['required', 'string', 'max:255', Rule::unique($branchTable, 'name')->ignore($branchId)],
            'foreign_name' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
        ]);
    }

    private function branchValidationTable(): string
    {
        $table = (new Branch())->getTable();

        if (tenant()) {
            return tenant()->database_connection_name . '.' . $table;
        }

        return 'central.' . $table;
    }

    private function requiredTenant(Request $request): Tenant
    {
        $tenant = admin_current_tenant();

        abort_if(! $tenant, 404);

        return $tenant;
    }
}
