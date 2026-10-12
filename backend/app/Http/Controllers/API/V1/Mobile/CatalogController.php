<?php

namespace App\Http\Controllers\API\V1\Mobile;

use App\Http\Controllers\API\V1\Mobile\Concerns\BuildsMobilePayloads;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Item;
use App\Models\Slider;
use App\Repositories\CatalogReadRepository;
use App\Services\PromotionPricingService;
use App\Services\TenantCurrencyContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CatalogController extends Controller
{
    use BuildsMobilePayloads;

    public function __construct(
        protected PromotionPricingService $promotionPricing,
        protected CatalogReadRepository $catalog,
        protected TenantCurrencyContext $currencyContext
    ) {
    }

    public function currency()
    {
        $baseCurrency = $this->currencyContext->baseCurrency() ?? $this->catalog->currencies()->first();

        return response()->json([
            'success' => true,
            'data' => $baseCurrency ? $this->mobileCurrencyPayload($baseCurrency, true) : null,
        ]);
    }

    public function currencies()
    {
        $baseCurrency = $this->currencyContext->baseCurrency();
        $currencies = $this->catalog->currencies();

        return response()->json([
            'success' => true,
            'data' => $currencies->map(function (Currency $c) use ($baseCurrency) {
                $isDefault = $baseCurrency ? ($c->id === $baseCurrency->id) : ($c->code === 'USD');
                return $this->mobileCurrencyPayload($c, $isDefault);
            })->values(),
        ]);
    }

    public function branches()
    {
        $branches = $this->catalog->activeBranches();

        return response()->json([
            'success' => true,
            'data' => $branches->map(fn (Branch $branch) => $this->mobileBranchPayload($branch))->values(),
        ]);
    }

    public function banners(Request $request)
    {
        $type = strtolower((string) $request->get('type', $request->get('placement', 'mobile')));

        $placements = match ($type) {
            'web', 'website' => ['Website', 'web', 'All', 'all'],
            'second_screen', 'secondscreen', 'display', 'pos' => ['second_screen', 'SecondScreen', 'Display', 'POS', 'All', 'all'],
            'all' => ['Mobile', 'mobile', 'Website', 'web', 'second_screen', 'SecondScreen', 'Display', 'POS', 'All', 'all'],
            default => ['Mobile', 'mobile', 'All', 'all'],
        };

        $banners = $this->catalog->banners($placements);

        $origin = rtrim($request->getSchemeAndHttpHost(), '/');

        return response()->json([
            'success' => true,
            'type' => $type,
            'data' => $banners->map(function (Slider $banner) use ($origin) {
                $payload = $banner->toPromoSlidePayload();
                $mediaUrl = trim((string) ($payload['mediaUrl'] ?? ''));

                if ($mediaUrl !== '' && str_starts_with($mediaUrl, '//')) {
                    $mediaUrl = parse_url($origin, PHP_URL_SCHEME).':'.$mediaUrl;
                } elseif ($mediaUrl !== '' && ! preg_match('#^https?://#i', $mediaUrl)) {
                    $mediaUrl = $origin.'/'.ltrim($mediaUrl, '/');
                }

                $payload['image_url'] = $mediaUrl;
                unset($payload['mediaUrl'], $payload['media_url'], $payload['image']);

                return $payload;
            })->values(),
        ]);
    }

    public function categories(Request $request)
    {
        $search = trim((string) $request->get('search', ''));

        $categories = $this->catalog->categories(
            $search,
            $request->integer('per_page') ?: 20,
            max(1, $request->integer('page', 1))
        );

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

        $paginated = $this->catalog->products([
            'search' => $search,
            'sort' => $sort,
            'category_id' => $request->filled('category_id') ? (int) $request->get('category_id') : null,
            'branch_id' => $request->filled('branch_id') && (int) $request->get('branch_id') > 0
                ? (int) $request->get('branch_id')
                : null,
            'featured' => $request->boolean('featured', false),
            'new_arrival' => $request->boolean('new_arrival', false),
            'premium' => $request->boolean('premium', false),
            'try_on' => $request->boolean('try_on', false) || $request->boolean('is_try_on_enabled', false),
            'per_page' => $request->integer('per_page') ?: 20,
            'page' => max(1, $request->integer('page', 1)),
        ]);
        $paginatedItems = collect($paginated->items());
        $promotionPreviews = $this->promotionPricing->catalogPromotionPreviews($paginatedItems);

        return response()->json([
            'success' => true,
            'data' => $paginatedItems
                ->map(fn (Item $item) => $this->mobileItemPayload(
                    $item,
                    $promotionPreviews[(int) $item->id] ?? []
                ))
                ->values(),
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
        $product = $this->catalog->product((int) $item);
        $promotionPreviews = $this->promotionPricing->catalogPromotionPreviews(collect([$product]));

        return response()->json([
            'success' => true,
            'data' => $this->mobileItemPayload(
                $product,
                $promotionPreviews[(int) $product->id] ?? []
            ),
        ]);
    }

    public function priceCart(Request $request)
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => [
                'required',
                'integer',
                Rule::exists((new Item())->getTable(), 'id')->where(function ($query) {
                    $query->where('status', 'Active')->where('sale', true);
                }),
            ],
            'items.*.variant_id' => ['nullable', 'integer', 'min:1'],
            'items.*.uom_id' => ['nullable', 'integer', 'min:1'],
            'items.*.option_value_ids' => ['nullable', 'array'],
            'items.*.option_value_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'currency_mode' => ['nullable', 'string', Rule::in(['native', 'base'])],
        ], [
            'items.required' => 'Your cart is empty.',
            'items.*.item_id.exists' => 'One or more items in your cart are no longer available or inactive in this store.',
            'items.*.quantity.min' => 'Item quantity must be at least 1.',
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->promotionPricing->price(
                $validated['items'],
                ($validated['currency_mode'] ?? 'native') === 'base'
            ),
        ]);
    }
}
