<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AddressController extends Controller
{
    public function __construct()
    {
        $this->middleware('admin.permission:addresses.view')->only(['index']);
        $this->middleware('admin.permission:addresses.manage')->except(['index']);
    }

    public function index(Request $request): View
    {
        $selectedTenant = $this->requiredTenant($request);
        $search = trim((string) $request->get('search', ''));

        $addresses = Address::query()
            ->with('user')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery->where('label', 'like', "%{$search}%")
                        ->orWhere('recipient_name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('address_line', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%")
                        ->orWhere('note', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($userQuery) use ($search) {
                            $userQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('username', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByDesc('is_default')
            ->orderBy('label')
            ->orderByDesc('id')
            ->get();

        return view('admin.addresses.index', [
            'addresses' => $addresses,
            'search' => $search,
            'selectedTenant' => $selectedTenant,
            'canManageAddresses' => admin_has_permission('addresses.manage'),
        ]);
    }

    public function create(Request $request): View
    {
        $selectedTenant = $this->requiredTenant($request);

        return view('admin.addresses.create', [
            'address' => new Address([
                'label' => 'Home',
                'code' => '+855',
                'latitude' => 11.5564,
                'longitude' => 104.9282,
                'is_default' => false,
            ]),
            'selectedTenant' => $selectedTenant,
            'userOptions' => $this->userOptions(),
            'dialCodeOptions' => $this->dialCodeOptions(),
            'googleMapsApiKey' => (string) config('services.google_maps.key', ''),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->requiredTenant($request);
        $validated = $this->validateAddress($request);

        DB::transaction(function () use ($validated) {
            if ($validated['is_default']) {
                Address::query()
                    ->where('user_id', $validated['user_id'])
                    ->update(['is_default' => false]);
            }

            Address::query()->create($validated);
        });

        Address::flushQueryCache();

        return redirect()
            ->route('admin.addresses.index')
            ->with('status', 'Address created successfully.');
    }

    public function edit(Request $request, string $address): View
    {
        $selectedTenant = $this->requiredTenant($request);

        return view('admin.addresses.edit', [
            'address' => $this->addressQuery()->findOrFail((int) $address),
            'selectedTenant' => $selectedTenant,
            'userOptions' => $this->userOptions(),
            'dialCodeOptions' => $this->dialCodeOptions(),
            'googleMapsApiKey' => (string) config('services.google_maps.key', ''),
        ]);
    }

    public function update(Request $request, string $address): RedirectResponse
    {
        $this->requiredTenant($request);
        $addressModel = $this->addressQuery()->findOrFail((int) $address);
        $validated = $this->validateAddress($request, $addressModel);

        DB::transaction(function () use ($validated, $addressModel) {
            if ($validated['is_default']) {
                Address::query()
                    ->where('user_id', $validated['user_id'])
                    ->whereKeyNot($addressModel->id)
                    ->update(['is_default' => false]);
            }

            $addressModel->fill($validated);
            $addressModel->save();
        });

        Address::flushQueryCache();

        return redirect()
            ->route('admin.addresses.index')
            ->with('status', 'Address updated successfully.');
    }

    public function destroy(Request $request, string $address): RedirectResponse
    {
        $this->requiredTenant($request);
        $addressModel = $this->addressQuery()->findOrFail((int) $address);
        $addressModel->delete();
        Address::flushQueryCache();

        return redirect()
            ->route('admin.addresses.index')
            ->with('status', 'Address deleted successfully.');
    }

    private function validateAddress(Request $request, ?Address $address = null): array
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists($this->userValidationTable(), 'id')],
            'label' => ['required', 'string', 'max:100'],
            'recipient_name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'regex:/^\+\d{1,4}$/'],
            'phone' => ['required', 'string', 'max:30'],
            'address_line' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $validated['phone'] = $this->normalizePhone($validated['phone']);
        $validated['latitude'] = $request->filled('latitude') ? round((float) $request->input('latitude'), 7) : null;
        $validated['longitude'] = $request->filled('longitude') ? round((float) $request->input('longitude'), 7) : null;
        $validated['is_default'] = $request->boolean('is_default');

        return $validated;
    }

    private function requiredTenant(Request $request): Tenant
    {
        $tenant = admin_current_tenant();

        abort_if(! $tenant, 404);

        return $tenant;
    }

    private function userOptions()
    {
        return User::query()
            ->orderBy('name')
            ->orderBy('username')
            ->get(['id', 'name', 'username']);
    }

    private function dialCodeOptions(): array
    {
        return [
            '+855',
            '+66',
            '+84',
            '+65',
            '+60',
            '+1',
        ];
    }

    private function addressQuery()
    {
        return Address::query()->with('user');
    }

    private function userValidationTable(): string
    {
        $table = (new User())->getTable();

        if (tenant()) {
            return tenant()->database_connection_name . '.' . $table;
        }

        return 'central.' . $table;
    }

    private function normalizePhone(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        return preg_replace('/\s+/', '', trim($phone));
    }
}
