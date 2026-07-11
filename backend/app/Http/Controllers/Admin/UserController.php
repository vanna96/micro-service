<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    protected UserRepository $users;

    public function __construct(UserRepository $users)
    {
        $this->users = $users;
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->get('search', ''));

        return view('admin.users.index', [
            'users' => $this->users->getAdminListing($search),
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        return view('admin.users.create', [
            'user' => new User(),
            'tenants' => $this->users->getTenantOptions(),
            'selectedTenants' => [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateUser($request);
        $validated['password'] = Hash::make($validated['password']);
        $validated['phone'] = $this->normalizePhone($validated['phone'] ?? null);

        $this->users->createForAdmin($validated);

        return redirect()
            ->route('admin.users.index')
            ->with('status', 'User created successfully.');
    }

    public function edit(User $user): View
    {
        $user = $this->users->loadForAdminEdit($user);

        return view('admin.users.edit', [
            'user' => $user,
            'tenants' => $this->users->getTenantOptions(),
            'selectedTenants' => $user->tenants->pluck('id')->all(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $this->validateUser($request, $user);
        $validated['phone'] = $this->normalizePhone($validated['phone'] ?? null);

        if (! empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $this->users->updateForAdmin($user, $validated);

        return redirect()
            ->route('admin.users.index')
            ->with('status', 'User updated successfully.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->users->deleteForAdmin($user);

        return redirect()
            ->route('admin.users.index')
            ->with('status', 'User deleted successfully.');
    }

    private function validateUser(Request $request, ?User $user = null): array
    {
        $userId = $user?->id;
        $userTable = 'central.' . (new User())->getTable();
        $tenantTable = 'central.' . (new Tenant())->getTable();

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', Rule::unique($userTable, 'username')->ignore($userId)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique($userTable, 'email')->ignore($userId)],
            'phone' => ['nullable', 'string', 'max:20', Rule::unique($userTable, 'phone')->ignore($userId)],
            'profile' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:6', 'confirmed'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'country_code' => ['nullable', 'string', 'max:5'],
            'gender' => ['nullable', Rule::in(['Male', 'Female'])],
            'dob' => ['nullable', 'date'],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
            'tenants' => ['nullable', 'array'],
            'tenants.*' => ['required', Rule::exists($tenantTable, 'id')],
        ]);
    }

    private function normalizePhone(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $normalized = preg_replace('/\D+/', '', $phone);

        return ltrim((string) $normalized, '0');
    }
}
