<?php

namespace App\Http\Controllers\API\V1\Pos;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\PriceList;
use App\Models\RateIndexValue;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PosCustomerController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->get('search', ''));

        $priceLists = PriceList::query()
            ->where('status', 'Active')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        $type = trim((string) $request->get('type', Customer::TYPE_CUSTOMER));
        if (! in_array($type, array_merge(Customer::TYPES, ['all']), true)) {
            $type = Customer::TYPE_CUSTOMER;
        }

        $customers = Customer::query()
            ->with([
                'profile',
                'priceList' => fn ($query) => $query->where('status', 'Active'),
            ])
            ->where('status', 'Active')
            ->when($type !== 'all', function ($query) use ($type) {
                $query->where('type', $type);
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->get();

        $priceListPayload = fn (PriceList $priceList): array => [
            'id' => (int) $priceList->id,
            'code' => (string) $priceList->code,
            'name' => (string) $priceList->name,
            'pricing_method' => $priceList->header_pricing_method,
            'fixed_amount' => $priceList->header_pricing_method === 'fixed'
                ? (float) ($priceList->header_fixed_price ?? 0)
                : null,
            'discount_percent' => $priceList->header_pricing_method === 'discount'
                ? (int) ($priceList->header_discount_percent ?? 0)
                : 0,
            'is_default' => (bool) $priceList->is_default,
        ];

        $baseCurrency = tenant_base_currency();
        $currencies = Currency::query()
            ->where('status', 'Active')
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get();
        $tenant = tenant();
        $generalSettings = is_array($tenant?->general_settings)
            ? $tenant->general_settings
            : [];
        $tenantTimezone = (string) ($generalSettings['timezone'] ?? config('app.timezone', 'UTC'));
        $today = Carbon::now($tenantTimezone);
        $currencyRates = RateIndexValue::query()
            ->with('currency')
            ->where('dataset_type', 'exchange_rate')
            ->where('year', $today->year)
            ->where('month', $today->month)
            ->where('day', $today->day)
            ->get()
            ->filter(fn (RateIndexValue $rate): bool => $rate->currency !== null)
            ->unique(fn (RateIndexValue $rate): string => (string) $rate->currency->code)
            ->mapWithKeys(fn (RateIndexValue $rate): array => [
                strtoupper((string) $rate->currency->code) => (float) $rate->value,
            ])
            ->all();

        if ($baseCurrency) {
            $currencyRates[strtoupper((string) $baseCurrency->code)] = 1.0;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'customers' => $customers->map(fn (Customer $customer): array => [
                    'id' => (int) $customer->id,
                    'code' => (string) $customer->code,
                    'type' => (string) ($customer->type ?: Customer::TYPE_CUSTOMER),
                    'name' => (string) $customer->name,
                    'email' => (string) ($customer->email ?? ''),
                    'phone' => (string) ($customer->phone ?? ''),
                    'profile_image_url' => $customer->profile_image_url,
                    'price_list_id' => $customer->priceList ? (int) $customer->priceList->id : null,
                ])->values(),
                'price_lists' => $priceLists->map($priceListPayload)->values(),
                'currency' => $baseCurrency ? [
                    'code' => (string) $baseCurrency->code,
                    'symbol' => (string) $baseCurrency->symbol,
                    'decimal_places' => (int) $baseCurrency->decimal_places,
                ] : null,
                'currencies' => $currencies->map(fn (Currency $currency): array => [
                    'code' => (string) $currency->code,
                    'symbol' => (string) ($currency->symbol ?: $currency->code),
                    'decimal_places' => (int) $currency->decimal_places,
                ])->values(),
                'currency_rates' => $currencyRates,
                'company' => [
                    'name' => (string) ($generalSettings['store_name'] ?? $tenant?->alias ?? ''),
                    'email' => (string) ($generalSettings['contact_email'] ?? ''),
                    'phone' => (string) ($generalSettings['contact_phone'] ?? ''),
                    'address' => (string) ($generalSettings['address'] ?? ''),
                    'receipt_footer' => (string) ($generalSettings['receipt_footer'] ?? ''),
                ],
            ],
        ]);
    }
}
