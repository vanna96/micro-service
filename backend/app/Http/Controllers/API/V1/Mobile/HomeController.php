<?php

namespace App\Http\Controllers\API\V1\Mobile;

use App\Http\Controllers\API\V1\Mobile\Concerns\BuildsMobilePayloads;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\Category;
use App\Models\Item;
use App\Models\Slider;
use App\Services\PromotionPricingService;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    use BuildsMobilePayloads;

    public function __construct(protected PromotionPricingService $promotionPricing)
    {
    }

    public function bootstrap(Request $request)
    {
        
        $tenant = tenant();
        $generalSettings = $tenant && is_array($tenant->general_settings) ? $tenant->general_settings : [];

        $hasBranchParam = $request->has('branch_id') && $request->input('branch_id') !== '' && (int) $request->input('branch_id') !== 0;
        $requestedBranchId = $hasBranchParam ? $request->integer('branch_id') : null;

        $branches = Branch::query()
            ->where('status', 'Active')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $selectedBranchId = null;
        if ($hasBranchParam) {
            $selectedBranchId = $branches->pluck('id')->contains($requestedBranchId)
                ? $requestedBranchId
                : $branches->first()?->id;
        }

        $baseItemsQuery = Item::query()
            ->with([
                'category',
                'branch',
                'currency',
                'image',
                'galleries',
                'uomGroup.units' => fn ($query) => $query->where('status', 'Active')->orderBy('sort_order'),
                'uomGroup.units.unit',
                'uomPrices',
                'optionGroups' => fn ($query) => $query->where('status', 'Active'),
                'optionGroups.values' => fn ($query) => $query->where('status', 'Active'),
                'variants' => fn ($query) => $query->where('status', 'Active'),
                'variants.optionValues',
            ])
            ->where('status', 'Active')
            ->where('sale', true)
            ->when($selectedBranchId, fn ($query) => $query->where('branch_id', $selectedBranchId));

        $banners = Slider::query()
            ->with('image')
            ->where('status', 'Active')
            ->whereIn('placement', ['Mobile', 'Website'])
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        $categories = Category::query()
            ->with('image')
            ->where('status', 'Active')
            ->orderByDesc('id')
            ->limit(12)
            ->get();

        $featured = (clone $baseItemsQuery)
            ->where('is_featured', true)
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        $newArrivals = (clone $baseItemsQuery)
            ->where('is_new_arrival', true)
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        $bestSellers = (clone $baseItemsQuery)
            ->orderByDesc('review_count')
            ->orderByDesc('rating')
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        $recommended = (clone $baseItemsQuery)
            ->where(function ($query) {
                $query->where('is_premium', true)
                    ->orWhere('is_featured', true);
            })
            ->orderByDesc('is_premium')
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        $allItems = $featured->concat($newArrivals)->concat($bestSellers)->concat($recommended)->unique('id');
        $promotionPreviews = $this->promotionPricing->catalogPromotionPreviews($allItems);

        $baseCurrency = tenant_base_currency();
        $currencies = Currency::query()
            ->where('status', 'Active')
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get();

        $currencyPayload = $baseCurrency ? $this->mobileCurrencyPayload($baseCurrency, true) : null;
        $currenciesPayload = $currencies->map(function (Currency $c) use ($baseCurrency) {
            $isDefault = $baseCurrency ? ($c->id === $baseCurrency->id) : ($c->code === 'USD');
            return $this->mobileCurrencyPayload($c, $isDefault);
        })->values();

        return response()->json([
            'success' => true,
            'data' => [
                'currency' => $currencyPayload,
                'currencies' => $currenciesPayload,
                'khr_exchange_rate' => 4100.0,
                'mobile_version' => [
                    'minimum_version' => $generalSettings['minimum_mobile_version'] ?? '1.0.0',
                    'latest_version' => $generalSettings['latest_mobile_version'] ?? '1.0.0',
                    'store_url_ios' => $generalSettings['store_url_ios'] ?? '',
                    'store_url_android' => $generalSettings['store_url_android'] ?? '',
                ],
                'selected_branch_id' => $selectedBranchId,
                'branches' => $branches->map(fn (Branch $branch) => $this->mobileBranchPayload($branch))->values(),
                'banners' => $banners->map(fn (Slider $banner) => $this->mobileBannerPayload($banner))->values(),
                'categories' => $categories->map(fn (Category $category) => $this->mobileCategoryPayload($category))->values(),
                'featured_products' => $featured->map(fn (Item $item) => $this->mobileItemPayload($item, $promotionPreviews[(int) $item->id] ?? []))->values(),
                'new_arrivals' => $newArrivals->map(fn (Item $item) => $this->mobileItemPayload($item, $promotionPreviews[(int) $item->id] ?? []))->values(),
                'best_sellers' => $bestSellers->map(fn (Item $item) => $this->mobileItemPayload($item, $promotionPreviews[(int) $item->id] ?? []))->values(),
                'recommended_products' => $recommended->map(fn (Item $item) => $this->mobileItemPayload($item, $promotionPreviews[(int) $item->id] ?? []))->values(),
            ],
        ]);
    }

    public function legal(Request $request)
    {
        $tenant = tenant();
        $generalSettings = $tenant && is_array($tenant->general_settings) ? $tenant->general_settings : [];

        return response()->json([
            'success' => true,
            'data' => [
                'terms_conditions' => $generalSettings['terms_conditions'] ?? '',
                'privacy_policy' => $generalSettings['privacy_policy'] ?? '',
            ]
        ]);
    }

}