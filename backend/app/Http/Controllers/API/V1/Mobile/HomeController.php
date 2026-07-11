<?php

namespace App\Http\Controllers\API\V1\Mobile;

use App\Http\Controllers\API\V1\Mobile\Concerns\BuildsMobilePayloads;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Item;
use App\Models\Slider;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    use BuildsMobilePayloads;

    public function bootstrap(Request $request)
    {
        $selectedBranchId = $request->integer('branch_id') ?: null;

        $branches = Branch::query()
            ->where('status', 'Active')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        if (! $branches->pluck('id')->contains($selectedBranchId)) {
            $selectedBranchId = $branches->first()?->id;
        }

        $baseItemsQuery = Item::query()
            ->with(['category', 'branch', 'currency', 'image', 'galleries'])
            ->where('status', 'Active')
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

        return response()->json([
            'success' => true,
            'data' => [
                'selected_branch_id' => $selectedBranchId,
                'branches' => $branches->map(fn (Branch $branch) => $this->mobileBranchPayload($branch))->values(),
                'banners' => $banners->map(fn (Slider $banner) => $this->mobileBannerPayload($banner))->values(),
                'categories' => $categories->map(fn (Category $category) => $this->mobileCategoryPayload($category))->values(),
                'featured_products' => $featured->map(fn (Item $item) => $this->mobileItemPayload($item))->values(),
                'new_arrivals' => $newArrivals->map(fn (Item $item) => $this->mobileItemPayload($item))->values(),
                'best_sellers' => $bestSellers->map(fn (Item $item) => $this->mobileItemPayload($item))->values(),
                'recommended_products' => $recommended->map(fn (Item $item) => $this->mobileItemPayload($item))->values(),
            ],
        ]);
    }
}
