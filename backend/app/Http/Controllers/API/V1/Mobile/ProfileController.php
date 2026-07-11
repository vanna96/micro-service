<?php

namespace App\Http\Controllers\API\V1\Mobile;

use App\Http\Controllers\API\V1\Mobile\Concerns\BuildsMobilePayloads;
use App\Http\Controllers\API\V1\Mobile\Concerns\InteractsWithMobileUsers;
use App\Http\Controllers\Controller;
use App\Rules\Base64Image;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use App\Models\User;

class ProfileController extends Controller
{
    use BuildsMobilePayloads;
    use InteractsWithMobileUsers;

    public function show(Request $request)
    {
        $centralUser = $this->currentCentralUser($request);
        $this->ensureTenantAccess($centralUser);
        $this->ensureTenantUserMirror($centralUser);

        return response()->json([
            'success' => true,
            'data' => $this->mobileUserPayload($centralUser),
        ]);
    }

    public function update(Request $request)
    {
        $centralUser = $this->currentCentralUser($request);
        $this->ensureTenantAccess($centralUser);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'username' => ['sometimes', 'string', 'max:255', Rule::unique($this->centralUsersTable(), 'username')->ignore($centralUser->id)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique($this->centralUsersTable(), 'email')->ignore($centralUser->id)],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'country_code' => ['nullable', 'string', 'max:10'],
            'phone' => ['sometimes', 'string', 'max:30', Rule::unique($this->centralUsersTable(), 'phone')->ignore($centralUser->id)],
            'password' => ['nullable', 'string', 'min:6'],
            'profile' => ['nullable', new Base64Image()],
            'gender' => ['nullable', Rule::in(['Male', 'Female'])],
            'dob' => ['nullable', 'date'],
        ]);

        DB::connection('central')->transaction(function () use ($centralUser, $validated) {
            if (filled($validated['profile'] ?? null)) {
                $this->replaceProfileImage($centralUser, (string) $validated['profile']);
                unset($validated['profile']);
            }

            if (filled($validated['password'] ?? null)) {
                $validated['password'] = Hash::make((string) $validated['password']);
            } else {
                unset($validated['password']);
            }

            $centralUser->fill($validated);

            if (! isset($validated['name']) && (isset($validated['first_name']) || isset($validated['last_name']))) {
                $centralUser->name = trim(implode(' ', array_filter([
                    $validated['first_name'] ?? $centralUser->first_name,
                    $validated['last_name'] ?? $centralUser->last_name,
                ]))) ?: $centralUser->name;
            }

            $centralUser->save();
        });

        $this->ensureTenantUserMirror($centralUser->fresh());
        User::flushQueryCache();

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'data' => $this->mobileUserPayload($centralUser->fresh()),
        ]);
    }

    protected function replaceProfileImage($centralUser, string $profileBase64): void
    {
        if ($centralUser->profile_id) {
            $oldGallery = $centralUser->galleries()->find($centralUser->profile_id);

            if ($oldGallery) {
                if (Storage::disk('user')->exists($oldGallery->name)) {
                    Storage::disk('user')->delete($oldGallery->name);
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

        $fileName = 'user_' . uniqid('', true) . '.' . $extension;
        Storage::disk('user')->put($fileName, $imageData, 'public');

        $gallery = $centralUser->galleries()->create([
            'type' => 'thumbnail',
            'status' => 'Active',
            'name' => $fileName,
        ]);

        $centralUser->profile_id = $gallery->id;
        $centralUser->save();
    }
}
