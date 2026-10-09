<?php

namespace App\Http\Controllers\API\V1\Mobile;

use App\Http\Controllers\API\V1\Mobile\Concerns\BuildsMobilePayloads;
use App\Http\Controllers\API\V1\Mobile\Concerns\InteractsWithMobileUsers;
use App\Http\Controllers\Controller;
use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AddressController extends Controller
{
    use BuildsMobilePayloads;
    use InteractsWithMobileUsers;

    public function index(Request $request)
    {
        $customer = $this->currentCustomer($request);

        $addresses = Address::query()
            ->where('customer_id', $customer->id)
            ->orderByDesc('is_default')
            ->orderBy('label')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $addresses->map(fn (Address $address) => $this->mobileAddressPayload($address))->values(),
        ]);
    }

    public function store(Request $request)
    {
        $customer = $this->currentCustomer($request);

        $validated = $this->validateAddressPayload($request);

        $address = DB::transaction(function () use ($customer, $validated) {
            if ($validated['is_default']) {
                Address::query()
                    ->where('customer_id', $customer->id)
                    ->update(['is_default' => false]);
            }

            return Address::query()->create(array_merge($validated, [
                'customer_id' => $customer->id,
            ]));
        });

        return response()->json([
            'success' => true,
            'message' => 'Address created successfully.',
            'data' => $this->mobileAddressPayload($address),
        ], 201);
    }

    public function update(Request $request, string $address)
    {
        $customer = $this->currentCustomer($request);

        $addressModel = Address::query()
            ->where('customer_id', $customer->id)
            ->findOrFail((int) $address);

        $validated = $this->validateAddressPayload($request);

        DB::transaction(function () use ($customer, $addressModel, $validated) {
            if ($validated['is_default']) {
                Address::query()
                    ->where('customer_id', $customer->id)
                    ->whereKeyNot($addressModel->id)
                    ->update(['is_default' => false]);
            }

            $addressModel->fill($validated);
            $addressModel->save();
        });

        return response()->json([
            'success' => true,
            'message' => 'Address updated successfully.',
            'data' => $this->mobileAddressPayload($addressModel->fresh()),
        ]);
    }

    public function delete(Request $request, string $address)
    {
        $customer = $this->currentCustomer($request);

        $addressModel = Address::query()
            ->where('customer_id', $customer->id)
            ->findOrFail((int) $address);

        $deletedWasDefault = (bool) $addressModel->is_default;
        $addressModel->delete();

        if ($deletedWasDefault) {
            $replacement = Address::query()
                ->where('customer_id', $customer->id)
                ->orderByDesc('id')
                ->first();

            if ($replacement) {
                $replacement->forceFill(['is_default' => true])->save();
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Address deleted successfully.',
        ]);
    }

    public function makeDefault(Request $request, string $address)
    {
        $customer = $this->currentCustomer($request);

        $addressModel = Address::query()
            ->where('customer_id', $customer->id)
            ->findOrFail((int) $address);

        DB::transaction(function () use ($customer, $addressModel) {
            Address::query()
                ->where('customer_id', $customer->id)
                ->update(['is_default' => false]);

            $addressModel->forceFill(['is_default' => true])->save();
        });

        return response()->json([
            'success' => true,
            'message' => 'Default address updated successfully.',
            'data' => $this->mobileAddressPayload($addressModel->fresh()),
        ]);
    }

    private function validateAddressPayload(Request $request): array
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:100'],
            'recipient_name' => ['required', 'string', 'max:255'],
            'country_code' => ['required', 'string', 'regex:/^\+\d{1,4}$/'],
            'phone' => ['required', 'string', 'max:30'],
            'address_line' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        return [
            'label' => $validated['label'],
            'recipient_name' => $validated['recipient_name'],
            'code' => $validated['country_code'],
            'phone' => preg_replace('/\s+/', '', trim($validated['phone'])),
            'address_line' => $validated['address_line'],
            'city' => $validated['city'],
            'note' => $validated['note'] ?? null,
            'latitude' => $request->filled('latitude') ? round((float) $request->input('latitude'), 7) : null,
            'longitude' => $request->filled('longitude') ? round((float) $request->input('longitude'), 7) : null,
            'is_default' => $request->boolean('is_default'),
        ];
    }
}
