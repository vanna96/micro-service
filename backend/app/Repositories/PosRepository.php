<?php

namespace App\Repositories;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Currency;
use App\Models\Item;
use App\Models\PriceList;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PosRepository extends RepositoryBase
{
    protected $repositoryId = 'rinvex.repository.id';
    protected $model = 'App\Models\Item';

    public function getWorkspace(?int $branchId = null, ?int $categoryId = null, string $search = ''): array
    {
        $search = trim($search);
        $branches = $this->branchModel()
            ->newQuery()
            ->where('status', 'Active')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $categories = $this->categoryModel()
            ->newQuery()
            ->where('status', 'Active')
            ->orderBy('name')
            ->get();

        $branchId = $branches->pluck('id')->contains($branchId) ? $branchId : null;
        $categoryId = $categories->pluck('id')->contains($categoryId) ? $categoryId : null;

        $allItems = $this->itemModel()
            ->newQuery()
            ->with(['branch', 'category', 'currency', 'image'])
            ->where('status', 'Active')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Item $item) => $this->mapItem($item))
            ->values();

        $baseFilteredItems = $this->filterItems($allItems, $branchId, null, $search);
        $visibleItems = $this->filterItems($allItems, $branchId, $categoryId, $search);
        $baseCurrency = tenant_base_currency();
        $customers = $this->customerModel()
            ->newQuery()
            ->where('status', 'Active')
            ->orderBy('name')
            ->get();
        $priceLists = $this->priceListModel()
            ->newQuery()
            ->where('status', 'Active')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return [
            'branches' => $branches->map(fn (Branch $branch) => [
                'id' => (int) $branch->id,
                'name' => (string) $branch->name,
            ])->values(),
            'categories' => $categories->map(function (Category $category) use ($baseFilteredItems) {
                return [
                    'id' => (int) $category->id,
                    'name' => (string) $category->name,
                    'count' => $baseFilteredItems->where('category_id', (int) $category->id)->count(),
                ];
            })->values(),
            'all_items' => $allItems,
            'visible_items' => $visibleItems->values(),
            'selected_branch_id' => $branchId,
            'selected_category_id' => $categoryId,
            'search' => $search,
            'base_currency' => $this->mapCurrency($baseCurrency),
            'customers' => $customers->map(fn (Customer $customer) => [
                'id' => (int) $customer->id,
                'name' => (string) $customer->name,
                'phone' => (string) ($customer->phone ?? ''),
                'email' => (string) ($customer->email ?? ''),
            ])->values(),
            'price_lists' => $priceLists->map(fn (PriceList $priceList) => [
                'id' => (int) $priceList->id,
                'name' => (string) $priceList->name,
                'code' => (string) ($priceList->code ?? ''),
                'is_default' => (bool) $priceList->is_default,
                'summary' => (string) ($priceList->header_pricing_summary ?? ''),
            ])->values(),
            'has_items' => $allItems->isNotEmpty(),
            'has_visible_items' => $visibleItems->isNotEmpty(),
        ];
    }

    public function getActiveItemIds(array $itemIds): Collection
    {
        return $this->itemModel()
            ->newQuery()
            ->where('status', 'Active')
            ->whereIn('id', collect($itemIds)->map(fn ($id) => (int) $id)->filter()->unique()->values())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();
    }

    protected function filterItems(Collection $items, ?int $branchId = null, ?int $categoryId = null, string $search = ''): Collection
    {
        $normalizedSearch = Str::lower(trim($search));

        return $items->filter(function (array $item) use ($branchId, $categoryId, $normalizedSearch) {
            if ($branchId && $item['branch_id'] !== $branchId) {
                return false;
            }

            if ($categoryId && $item['category_id'] !== $categoryId) {
                return false;
            }

            if ($normalizedSearch === '') {
                return true;
            }

            $haystacks = [
                $item['name'],
                $item['foreign_name'],
                $item['sku'],
                $item['category_name'],
                $item['branch_name'],
            ];

            return collect($haystacks)
                ->filter(fn (?string $value) => filled($value))
                ->contains(fn (string $value) => Str::contains(Str::lower($value), $normalizedSearch));
        })->values();
    }

    protected function mapItem(Item $item): array
    {
        $currency = $item->relationLoaded('currency') ? $item->getRelation('currency') : $item->currency()->first();
        $displayPrice = round_currency_amount($item->price, $currency);

        return [
            'id' => (int) $item->id,
            'sku' => (string) $item->sku,
            'name' => (string) $item->name,
            'foreign_name' => (string) ($item->foreign_name ?? ''),
            'category_id' => $item->category_id ? (int) $item->category_id : null,
            'category_name' => (string) ($item->category?->name ?? 'Uncategorized'),
            'branch_id' => $item->branch_id ? (int) $item->branch_id : null,
            'branch_name' => (string) ($item->branch?->name ?? $item->branch_name ?? 'All Branches'),
            'image_url' => $item->image_url,
            'display_price' => (float) $displayPrice,
            'display_price_label' => format_currency_amount($displayPrice, $currency, (bool) ($currency?->code ?? null)),
            'currency' => $this->mapCurrency($currency),
            'stock' => (int) ($item->stock ?? 0),
            'is_available' => true,
            'placeholder' => $this->placeholderFor($item->name),
            'accent' => $this->accentFor((int) $item->id),
        ];
    }

    protected function mapCurrency(?Currency $currency): ?array
    {
        if (! $currency) {
            return null;
        }

        return [
            'code' => strtoupper(trim((string) $currency->code)),
            'decimals' => currency_decimal_places($currency),
        ];
    }

    protected function placeholderFor(string $value): string
    {
        $segments = collect(preg_split('/\s+/', trim($value)) ?: [])
            ->filter()
            ->take(2)
            ->map(fn (string $segment) => Str::upper(Str::substr($segment, 0, 1)));

        return $segments->implode('') ?: 'IT';
    }

    protected function accentFor(int $id): string
    {
        $palette = ['mint', 'sky', 'amber', 'coral', 'violet', 'ink'];

        return $palette[$id % count($palette)];
    }

    protected function itemModel(): Item
    {
        /** @var \App\Models\Item $model */
        $model = $this->createModel();

        if (tenant()) {
            $model->setConnection(tenant()->database_connection_name);
        }

        return $model;
    }

    protected function branchModel(): Branch
    {
        $model = new Branch();

        if (tenant()) {
            $model->setConnection(tenant()->database_connection_name);
        }

        return $model;
    }

    protected function categoryModel(): Category
    {
        $model = new Category();

        if (tenant()) {
            $model->setConnection(tenant()->database_connection_name);
        }

        return $model;
    }

    protected function customerModel(): Customer
    {
        $model = new Customer();

        if (tenant()) {
            $model->setConnection(tenant()->database_connection_name);
        }

        return $model;
    }

    protected function priceListModel(): PriceList
    {
        $model = new PriceList();

        if (tenant()) {
            $model->setConnection(tenant()->database_connection_name);
        }

        return $model;
    }
}
