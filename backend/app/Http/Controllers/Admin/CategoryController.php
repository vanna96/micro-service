<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Tenant;
use App\Repositories\CategoryRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CategoryController extends Controller
{
    protected CategoryRepository $categories;

    public function __construct(CategoryRepository $categories)
    {
        $this->categories = $categories;
        $this->middleware('admin.permission:categories.view')->only(['index']);
        $this->middleware('admin.permission:categories.manage')->except(['index']);
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->get('search', ''));
        $selectedTenant = $this->requiredTenant($request);

        $categories = $this->categories->getAdminListing($search);

        return view('admin.categories.index', [
            'categories' => $categories,
            'search' => $search,
            'selectedTenant' => $selectedTenant,
        ]);
    }

    public function create(Request $request): View
    {
        $selectedTenant = $this->requiredTenant($request);

        return view('admin.categories.create', [
            'category' => new Category(),
            'parentOptions' => $this->categories->getParentOptions(),
            'selectedTenant' => $selectedTenant,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->requiredTenant($request);
        $validated = $this->validateCategory($request);
        $this->categories->createForAdmin($validated);

        return redirect()
            ->route('admin.categories.index')
            ->with('status', 'Category created successfully.');
    }

    public function edit(Request $request, string $category): View
    {
        $selectedTenant = $this->requiredTenant($request);

        $categoryModel = $this->categories->loadForAdminEdit((int) $category);

        return view('admin.categories.edit', [
            'category' => $categoryModel,
            'parentOptions' => $this->categories->getParentOptions($categoryModel),
            'selectedTenant' => $selectedTenant,
        ]);
    }

    public function update(Request $request, string $category): RedirectResponse
    {
        $this->requiredTenant($request);
        $categoryModel = $this->categories->loadForAdminEdit((int) $category);
        $validated = $this->validateCategory($request, $categoryModel);
        $this->categories->updateForAdmin($categoryModel, $validated);

        return redirect()
            ->route('admin.categories.index')
            ->with('status', 'Category updated successfully.');
    }

    public function destroy(Request $request, string $category): RedirectResponse
    {
        $this->requiredTenant($request);
        $categoryModel = $this->categories->loadForAdminEdit((int) $category);
        $this->categories->deleteForAdmin($categoryModel);

        return redirect()
            ->route('admin.categories.index')
            ->with('status', 'Category deleted successfully.');
    }

    private function validateCategory(Request $request, ?Category $category = null): array
    {
        $categoryId = $category?->id;
        $categoryTable = $this->categoryValidationTable();

        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique($categoryTable, 'name')->ignore($categoryId)],
            'foreign_name' => ['nullable', 'string', 'max:255'],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists($categoryTable, 'id'),
                Rule::notIn([$categoryId]),
            ],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
        ]);
    }

    private function categoryValidationTable(): string
    {
        $table = (new Category())->getTable();

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
