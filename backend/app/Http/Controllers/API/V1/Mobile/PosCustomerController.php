<?php

namespace App\Http\Controllers\API\V1\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\PriceList;
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

        $customers = Customer::query()
            ->with([
                'profile',
                'priceList' => fn ($query) => $query->where('status', 'Active'),
            ])
            ->where('status', 'Active')
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
            'discount_percent' => $priceList->header_pricing_method === 'discount'
                ? (int) ($priceList->header_discount_percent ?? 0)
                : 0,
            'is_default' => (bool) $priceList->is_default,
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'customers' => $customers->map(fn (Customer $customer): array => [
                    'id' => (int) $customer->id,
                    'code' => (string) $customer->code,
                    'name' => (string) $customer->name,
                    'email' => (string) ($customer->email ?? ''),
                    'phone' => (string) ($customer->phone ?? ''),
                    'profile_image_url' => $customer->profile_image_url,
                    'price_list_id' => $customer->priceList ? (int) $customer->priceList->id : null,
                ])->values(),
                'price_lists' => $priceLists->map($priceListPayload)->values(),
            ],
        ]);
    }
}
