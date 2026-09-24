<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ItemOption;
use App\Models\ItemVariation;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ItemOptionController extends Controller
{
    public function __construct()
    {
        $this->middleware('admin.permission:item_options.view')->only(['index']);
        $this->middleware('admin.permission:item_options.create')->only(['create', 'store']);
        $this->middleware('admin.permission:item_options.edit')->only(['edit', 'update']);
        $this->middleware('admin.permission:item_options.delete')->only(['destroy']);
    }

    public function index(Request $request): View
    {
        $selectedTenant = $this->requiredTenant();
        $search = trim((string) $request->get('search', ''));
        $options = ItemOption::query()
            ->with('variation')
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('item_variation_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('admin.item-options.index', [
            'options' => $options,
            'search' => $search,
            'selectedTenant' => $selectedTenant,
            'baseCurrency' => tenant_base_currency(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.item-options.create', [
            'option' => new ItemOption([
                'item_variation_id' => $request->integer('variation'),
                'price_adjustment' => 0,
                'status' => 'Active',
            ]),
            'variationOptions' => $this->variationOptions(),
            'selectedTenant' => $this->requiredTenant(),
            'baseCurrency' => tenant_base_currency(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->requiredTenant();
        ItemOption::query()->create($this->validated($request));
        ItemOption::flushQueryCache();

        return redirect()->route('admin.item-options.index')->with('status', 'Option created successfully.');
    }

    public function edit(string $item_option): View
    {
        return view('admin.item-options.edit', [
            'option' => $this->find($item_option),
            'variationOptions' => $this->variationOptions(),
            'selectedTenant' => $this->requiredTenant(),
            'baseCurrency' => tenant_base_currency(),
        ]);
    }

    public function update(Request $request, string $item_option): RedirectResponse
    {
        $this->requiredTenant();
        $option = $this->find($item_option);
        $option->update($this->validated($request, $option));
        ItemOption::flushQueryCache();

        return redirect()->route('admin.item-options.index')->with('status', 'Option updated successfully.');
    }

    public function destroy(string $item_option): RedirectResponse
    {
        $this->requiredTenant();
        $this->find($item_option)->delete();
        ItemOption::flushQueryCache();

        return redirect()->route('admin.item-options.index')->with('status', 'Option deleted successfully.');
    }

    private function validated(Request $request, ?ItemOption $option = null): array
    {
        $request->merge(['is_default' => $request->boolean('is_default')]);
        $variationId = (int) $request->input('item_variation_id');

        $validated = $request->validate([
            'item_variation_id' => ['required', 'integer', Rule::exists($this->variationTable(), 'id')],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique($this->optionTable(), 'name')
                    ->where(fn ($query) => $query->where('item_variation_id', $variationId))
                    ->ignore($option?->id),
            ],
            'foreign_name' => ['nullable', 'string', 'max:255'],
            'sku_suffix' => ['nullable', 'string', 'max:64'],
            'color_hex' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}([0-9A-Fa-f]{2})?$/'],
            'price_adjustment' => ['nullable', 'numeric'],
            'is_default' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
        ]);

        $variation = ItemVariation::query()->find($variationId);
        if ($variation?->type !== 'modifier') {
            $validated['is_default'] = false;
        }

        $validated['price_adjustment'] = format_currency_input(
            $validated['price_adjustment'] ?? 0,
            tenant_base_currency(),
            2
        );

        return $validated;
    }

    private function variationOptions()
    {
        return ItemVariation::query()->orderBy('name')->get();
    }

    private function find(string $id): ItemOption
    {
        return ItemOption::query()->findOrFail((int) $id);
    }

    private function variationTable(): string
    {
        return tenant()->database_connection_name.'.'.(new ItemVariation())->getTable();
    }

    private function optionTable(): string
    {
        return tenant()->database_connection_name.'.'.(new ItemOption())->getTable();
    }

    private function requiredTenant(): Tenant
    {
        $tenant = admin_current_tenant();
        abort_if(! $tenant, 404);

        return $tenant;
    }
}
