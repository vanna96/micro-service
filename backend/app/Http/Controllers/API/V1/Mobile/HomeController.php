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

class HomeController extends Controller
{
    use BuildsMobilePayloads;

    public function __construct(
        protected PromotionPricingService $promotionPricing,
        protected CatalogReadRepository $catalog,
        protected TenantCurrencyContext $currencyContext
    ) {
    }

    public function bootstrap(Request $request)
    {
        
        $tenant = tenant();
        $generalSettings = $tenant && is_array($tenant->general_settings) ? $tenant->general_settings : [];

        $hasBranchParam = $request->has('branch_id') && $request->input('branch_id') !== '' && (int) $request->input('branch_id') !== 0;
        $requestedBranchId = $hasBranchParam ? $request->integer('branch_id') : null;

        $branches = $this->catalog->activeBranches();

        $selectedBranchId = null;
        if ($hasBranchParam) {
            $selectedBranchId = $branches->pluck('id')->contains($requestedBranchId)
                ? $requestedBranchId
                : $branches->first()?->id;
        }

        $banners = $this->catalog->banners(['Mobile', 'Website'], 10);
        $categories = $this->catalog->featuredCategories(12);
        $productSections = $this->catalog->bootstrapProducts($selectedBranchId);
        $featured = $productSections['featured_products'];
        $newArrivals = $productSections['new_arrivals'];
        $bestSellers = $productSections['best_sellers'];
        $recommended = $productSections['recommended_products'];

        $allItems = $featured->concat($newArrivals)->concat($bestSellers)->concat($recommended)->unique('id');
        $promotionPreviews = $this->promotionPricing->catalogPromotionPreviews($allItems);

        $baseCurrency = $this->currencyContext->baseCurrency();
        $currencies = $this->catalog->currencies();

        $currencyPayload = $baseCurrency ? $this->mobileCurrencyPayload($baseCurrency, true) : null;
        $currenciesPayload = $currencies->map(function (Currency $c) use ($baseCurrency) {
            $isDefault = $baseCurrency ? ($c->id === $baseCurrency->id) : ($c->code === 'USD');
            return $this->mobileCurrencyPayload($c, $isDefault);
        })->values();

        return response()->json([
            'success' => true,
            'data' => [
                'store' => \App\Http\Controllers\API\V1\PublicStoreController::summary($tenant),
                'currency' => $currencyPayload,
                'currencies' => $currenciesPayload,
                'khr_exchange_rate' => data_get($currenciesPayload->firstWhere('code', 'KHR'), 'exchange_rate'),
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
                'contact_email' => $generalSettings['contact_email'] ?? '',
            ]
        ]);
    }

}
