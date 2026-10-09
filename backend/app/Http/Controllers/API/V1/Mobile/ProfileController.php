<?php

namespace App\Http\Controllers\API\V1\Mobile;

use App\Http\Controllers\API\V1\Mobile\Concerns\BuildsMobilePayloads;
use App\Http\Controllers\API\V1\Mobile\Concerns\InteractsWithMobileUsers;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Rules\Base64Image;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    use BuildsMobilePayloads;
    use InteractsWithMobileUsers;

    public function show(Request $request)
    {
        $customer = $this->currentCustomer($request);

        return response()->json([
            'success' => true,
            'data' => $this->mobileUserPayload($customer),
        ]);
    }

    public function update(Request $request)
    {
        $customer = $this->currentCustomer($request);

        if ($request->has('phone')) {
            $request->merge([
                'phone' => ltrim((string) preg_replace('/\D+/', '', (string) $request->input('phone')), '0'),
            ]);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'username' => ['sometimes', 'string', 'max:255', Rule::unique($this->customersTable(), 'username')->ignore($customer->id)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique($this->customersTable(), 'email')->ignore($customer->id)],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'country_code' => ['nullable', 'string', 'max:10'],
            'phone' => ['nullable', 'string', 'max:20', Rule::unique($this->customersTable(), 'phone')->ignore($customer->id)],
            'password' => ['nullable', 'string', 'min:6'],
            'profile' => ['nullable', new Base64Image()],
            'gender' => ['nullable', Rule::in(['Male', 'Female'])],
            'dob' => ['nullable', 'date'],
        ]);

        DB::transaction(function () use ($customer, $validated) {
            if (filled($validated['profile'] ?? null)) {
                $this->replaceProfileImage($customer, (string) $validated['profile']);
                unset($validated['profile']);
            }

            if (filled($validated['password'] ?? null)) {
                $validated['password'] = Hash::make((string) $validated['password']);
            } else {
                unset($validated['password']);
            }

            $customer->fill($validated);

            if (! isset($validated['name']) && (isset($validated['first_name']) || isset($validated['last_name']))) {
                $customer->name = trim(implode(' ', array_filter([
                    $validated['first_name'] ?? $customer->first_name,
                    $validated['last_name'] ?? $customer->last_name,
                ]))) ?: $customer->name;
            }

            $customer->save();
        });

        Customer::flushQueryCache();

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'data' => $this->mobileUserPayload($customer->fresh()),
        ]);
    }

    protected function replaceProfileImage(Customer $customer, string $profileBase64): void
    {
        if ($customer->profile_id) {
            $oldGallery = $customer->galleries()->find($customer->profile_id);

            if ($oldGallery) {
                if (Storage::disk('customer')->exists($oldGallery->name)) {
                    Storage::disk('customer')->delete($oldGallery->name);
                }

                $oldGallery->delete();
            }
        }

        preg_match('/^data:image\/(\w+);base64,/', $profileBase64, $matches);
        $extension = isset($matches[1]) ? strtolower($matches[1]) : 'png';
        $base64Data = preg_replace('/^data:image\/\w+;base64,/', '', $profileBase64);
        $imageData = base64_decode((string) $base64Data, true);

        if (! $imageData) {
            return;
        }

        $fileName = 'customer_' . uniqid('', true) . '.' . $extension;
        Storage::disk('customer')->put($fileName, $imageData, 'public');

        $gallery = $customer->galleries()->create([
            'type' => 'thumbnail',
            'status' => 'Active',
            'name' => $fileName,
        ]);

        $customer->profile_id = $gallery->id;
        $customer->save();
    }
}
