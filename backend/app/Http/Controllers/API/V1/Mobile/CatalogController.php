<?php

namespace App\Http\Controllers\API\V1\Mobile;

use App\Http\Controllers\API\V1\Mobile\Concerns\BuildsMobilePayloads;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Item;
use App\Models\Slider;
use App\Services\PromotionPricingService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CatalogController extends Controller
{
    use BuildsMobilePayloads;

    public function __construct(protected PromotionPricingService $promotionPricing)
    {
    }

    public function branches()
    {
        $branches = Branch::query()
            ->where('status', 'Active')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $branches->map(fn (Branch $branch) => $this->mobileBranchPayload($branch))->values(),
        ]);
    }

    public function banners()
    {
        $banners = Slider::query()
            ->with('image')
            ->where('status', 'Active')
            ->whereIn('placement', ['Mobile', 'Website'])
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $banners->map(fn (Slider $banner) => $this->mobileBannerPayload($banner))->values(),
        ]);
    }

    public function categories(Request $request)
    {
        $search = trim((string) $request->get('search', ''));

        $categories = Category::query()
            ->with('image')
            ->where('status', 'Active')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('foreign_name', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('id')
            ->paginate($request->integer('per_page') ?: 20);

        return response()->json([
            'success' => true,
            'data' => collect($categories->items())->map(fn (Category $category) => $this->mobileCategoryPayload($category))->values(),
            'meta' => [
                'current_page' => $categories->currentPage(),
                'per_page' => $categories->perPage(),
                'total' => $categories->total(),
                'last_page' => $categories->lastPage(),
            ],
        ]);
    }

    public function products(Request $request)
    {
        $search = trim((string) $request->get('search', ''));
        $sort = (string) $request->get('sort', 'latest');

        $products = Item::query()
            ->with([
                'category',
                'branch',
                'currency',
                'image',
                'galleries',
                'uomGroup.units' => fn ($query) => $query->where('status', 'Active')->orderBy('sort_order'),
                'uomGroup.units.unit',
                'optionGroups' => fn ($query) => $query->where('status', 'Active'),
                'optionGroups.values' => fn ($query) => $query->where('status', 'Active'),
                'variants' => fn ($query) => $query->where('status', 'Active'),
                'variants.optionValues',
            ])
            ->where('status', 'Active')
            ->when($request->filled('category_id'), fn ($query) => $query->where('category_id', (int) $request->get('category_id')))
            ->when($request->filled('branch_id'), fn ($query) => $query->where('branch_id', (int) $request->get('branch_id')))
            ->when($request->boolean('featured', false), fn ($query) => $query->where('is_featured', true))
            ->when($request->boolean('new_arrival', false), fn ($query) => $query->where('is_new_arrival', true))
            ->when($request->boolean('premium', false), fn ($query) => $query->where('is_premium', true))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery->where('sku', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('foreign_name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('category', function ($categoryQuery) use ($search) {
                            $categoryQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('foreign_name', 'like', "%{$search}%");
                        });
                });
            });

        match ($sort) {
            'price_asc' => $products->orderBy('price'),
            'price_desc' => $products->orderByDesc('price'),
            'name_asc' => $products->orderBy('name'),
            'name_desc' => $products->orderByDesc('name'),
            default => $products->orderByDesc('id'),
        };

        $paginated = $products->paginate($request->integer('per_page') ?: 20);

        return response()->json([
            'success' => true,
            'data' => collect($paginated->items())->map(fn (Item $item) => $this->mobileItemPayload($item))->values(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
            ],
        ]);
    }

    public function show(string $item)
    {
        $product = Item::query()
            ->with([
                'category',
                'branch',
                'currency',
                'image',
                'galleries',
                'uomGroup.units' => fn ($query) => $query->where('status', 'Active')->orderBy('sort_order'),
                'uomGroup.units.unit',
                'optionGroups' => fn ($query) => $query->where('status', 'Active'),
                'optionGroups.values' => fn ($query) => $query->where('status', 'Active'),
                'variants' => fn ($query) => $query->where('status', 'Active'),
                'variants.optionValues',
            ])
            ->where('status', 'Active')
            ->findOrFail((int) $item);

        return response()->json([
            'success' => true,
            'data' => $this->mobileItemPayload($product),
        ]);
    }

    public function priceCart(Request $request)
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer', Rule::exists((new Item())->getTable(), 'id')],
            'items.*.variant_id' => ['nullable', 'integer', 'min:1'],
            'items.*.option_value_ids' => ['nullable', 'array'],
            'items.*.option_value_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->promotionPricing->price($validated['items']),
        ]);
    }
}
