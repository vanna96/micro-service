<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ItemVariation;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ItemVariationController extends Controller
{
    public function __construct()
    {
        $this->middleware('admin.permission:item_variations.view')->only(['index']);
        $this->middleware('admin.permission:item_variations.create')->only(['create', 'store']);
        $this->middleware('admin.permission:item_variations.edit')->only(['edit', 'update']);
        $this->middleware('admin.permission:item_variations.delete')->only(['destroy']);
    }

    public function index(Request $request): View
    {
        $selectedTenant = $this->requiredTenant();
        $search = trim((string) $request->get('search', ''));
        $variations = ItemVariation::query()
            ->withCount('options')
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->get();

        return view('admin.item-variations.index', compact('variations', 'search', 'selectedTenant'));
    }

    public function create(): View
    {
        return view('admin.item-variations.create', [
            'variation' => new ItemVariation([
                'type' => 'variant',
                'selection_type' => 'single',
                'is_required' => true,
                'min_selections' => 1,
                'max_selections' => 1,
                'status' => 'Active',
            ]),
            'selectedTenant' => $this->requiredTenant(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->requiredTenant();
        ItemVariation::query()->create($this->validated($request));
        ItemVariation::flushQueryCache();

        return redirect()->route('admin.item-variations.index')->with('status', 'Variation created successfully.');
    }

    public function edit(string $item_variation): View
    {
        return view('admin.item-variations.edit', [
            'variation' => $this->find($item_variation),
            'selectedTenant' => $this->requiredTenant(),
        ]);
    }

    public function update(Request $request, string $item_variation): RedirectResponse
    {
        $this->requiredTenant();
        $variation = $this->find($item_variation);
        $variation->update($this->validated($request, $variation));
        ItemVariation::flushQueryCache();

        return redirect()->route('admin.item-variations.index')->with('status', 'Variation updated successfully.');
    }

    public function destroy(string $item_variation): RedirectResponse
    {
        $this->requiredTenant();
        $this->find($item_variation)->delete();
        ItemVariation::flushQueryCache();

        return redirect()->route('admin.item-variations.index')->with('status', 'Variation deleted successfully.');
    }

    private function validated(Request $request, ?ItemVariation $variation = null): array
    {
        $request->merge(['is_required' => $request->boolean('is_required')]);
        $attributes = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique($this->validationTable(), 'name')->ignore($variation?->id)],
            'foreign_name' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::in(['variant', 'modifier'])],
            'selection_type' => ['required', Rule::in(['single', 'multiple'])],
            'is_required' => ['required', 'boolean'],
            'min_selections' => ['nullable', 'integer', 'min:0'],
            'max_selections' => ['nullable', 'integer', 'min:1', 'gte:min_selections'],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
        ]);

        if ($attributes['type'] === 'variant') {
            $attributes['selection_type'] = 'single';
            $attributes['is_required'] = true;
            $attributes['min_selections'] = 1;
            $attributes['max_selections'] = 1;
        }

        return $attributes;
    }

    private function find(string $id): ItemVariation
    {
        return ItemVariation::query()->findOrFail((int) $id);
    }

    private function validationTable(): string
    {
        return tenant()->database_connection_name.'.'.(new ItemVariation())->getTable();
    }

    private function requiredTenant(): Tenant
    {
        $tenant = admin_current_tenant();
        abort_if(! $tenant, 404);

        return $tenant;
    }
}
