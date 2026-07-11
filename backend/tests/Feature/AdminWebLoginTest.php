<?php

namespace Tests\Feature;

use App\Models\Gallery;
use App\Models\Branch;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminWebLoginTest extends TestCase
{
    protected string $databasePath;
    protected array $tenantDatabasePaths = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = storage_path('framework/testing/admin-web-login.sqlite');

        if (! File::exists(dirname($this->databasePath))) {
            File::makeDirectory(dirname($this->databasePath), 0755, true);
        }

        if (! File::exists($this->databasePath)) {
            File::put($this->databasePath, '');
        }

        $sqliteConnection = [
            'driver' => 'sqlite',
            'database' => $this->databasePath,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ];

        config()->set('database.default', 'central');
        config()->set('database.connections.central', $sqliteConnection);
        config()->set('database.connections.mysql', $sqliteConnection);

        DB::purge('central');
        DB::purge('mysql');

        Artisan::call('migrate:fresh', ['--database' => 'central']);
    }

    protected function tearDown(): void
    {
        if (isset($this->databasePath) && File::exists($this->databasePath)) {
            File::delete($this->databasePath);
        }

        foreach ($this->tenantDatabasePaths as $tenantDatabasePath) {
            if (File::exists($tenantDatabasePath)) {
                File::delete($tenantDatabasePath);
            }
        }

        parent::tearDown();
    }

    public function test_admin_login_page_is_available(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('Administrator')
            ->assertSee('Tenant User')
            ->assertSee('Choose how you want to sign in.')
            ->assertSee('administrator-login-tab', false)
            ->assertSee('tenant-login-tab', false)
            ->assertSee('administrator-login-pane', false)
            ->assertSee('tenant-login-pane', false)
            ->assertSee('Username, Email or Phone')
            ->assertSee('Tenant Code')
            ->assertDontSee('Forgot password?')
            ->assertSee('class="nav-link active"', false)
            ->assertSee('class="tab-pane fade show active"', false)
            ->assertSee('minible/assets/css/app.min.css');
    }

    public function test_admin_web_login_accepts_username(): void
    {
        $user = $this->createUser([
            'username' => 'admin-user',
            'email' => 'admin@example.com',
            'phone' => '12345678',
        ]);

        $this->post('/admin/login', [
            'login_scope' => 'administrator',
            'username' => 'admin-user',
            'password' => 'secret123',
        ])->assertRedirect('/home');

        $this->assertAuthenticatedAs($user);

        $this->get('/home')
            ->assertOk()
            ->assertSee('topnav-menu-content', false)
            ->assertSee('No tenant assigned')
            ->assertDontSee('Select Tenant')
            ->assertSee('Administrator')
            ->assertSee('Tenants')
            ->assertDontSee('Master Data')
            ->assertDontSee('Category')
            ->assertDontSee('Item')
            ->assertDontSee('Setting')
            ->assertDontSee('Promotion');
    }

    public function test_navbar_shows_tenant_switcher_for_users_with_assigned_tenants(): void
    {
        $user = $this->createUser([
            'username' => 'tenant-switch-user',
            'email' => 'tenant-switch@example.com',
            'phone' => '13345678',
        ]);
        $tenant = $this->createTenant('t-001');

        $user->tenants()->sync([$tenant->id]);

        $this->actingAs($user)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->get('/home')
            ->assertOk()
            ->assertSee('tenantSelectionModal', false)
            ->assertSee('Switch Tenant')
            ->assertSee($tenant->id);
    }

    public function test_administrator_with_assigned_tenants_only_sees_limited_menu_until_tenant_is_selected(): void
    {
        $user = $this->createUser([
            'username' => 'tenant-limited-user',
            'email' => 'tenant-limited@example.com',
            'phone' => '14345678',
        ]);
        $tenant = $this->createTenant('tenant-limited-menu');

        $user->tenants()->sync([$tenant->id]);

        $this->actingAs($user)
            ->get('/home')
            ->assertOk()
            ->assertSee('Select Tenant')
            ->assertSee('Administrator')
            ->assertSee('Tenants')
            ->assertDontSee('Master Data')
            ->assertDontSee('User Setting')
            ->assertDontSee('Promotion')
            ->assertDontSee('Setting')
            ->assertSee('only the Administrator and Tenants menus stay available');
    }

    public function test_admin_web_login_accepts_email(): void
    {
        $user = $this->createUser([
            'username' => 'admin-email',
            'email' => 'email@example.com',
            'phone' => '22345678',
        ]);

        $this->post('/admin/login', [
            'username' => 'email@example.com',
            'password' => 'secret123',
        ])->assertRedirect('/home');

        $this->assertAuthenticatedAs($user);
    }

    public function test_tenant_user_login_requires_tenant_selection_for_explicit_tenant_scope(): void
    {
        $this->followingRedirects()
            ->from('/admin/login')
            ->post('/admin/login', [
                'login_scope' => 'tenant',
                'username' => 'tenant-user',
                'password' => 'secret123',
            ])
            ->assertOk()
            ->assertSee('Tenant code is required for tenant user login.')
            ->assertSee('id="tenant-login-tab"', false)
            ->assertSee('aria-selected="true"', false)
            ->assertSee('id="tenant-login-pane"', false)
            ->assertSee('class="tab-pane fade show active"', false);

        $this->assertGuest();
    }

    public function test_tenant_user_can_login_with_selected_tenant(): void
    {
        $tenant = $this->createTenant('tenant-explicit-login');

        tenancy()->initialize($tenant);
        $user = User::query()->create([
            'name' => 'Tenant User',
            'username' => 'tenant-user-login',
            'email' => 'tenant-user@example.com',
            'phone' => '19998888',
            'password' => Hash::make('secret123'),
            'status' => 'Active',
        ]);
        $user->roles()->sync([$this->tenantRoleId($tenant, 'tenant-admin', false)]);
        tenancy()->end();
        DB::purge('tenant');

        $this->post('/admin/login', [
            'login_scope' => 'tenant',
            'tenant_code' => $tenant->id,
            'username' => 'tenant-user-login',
            'password' => 'secret123',
        ])->assertRedirect('/home');

        $this->assertAuthenticated();
        $this->assertSame('tenant', session('auth_user_scope'));
        $this->assertSame($tenant->id, session('auth_tenant_id'));

        $this->get('/home')
            ->assertOk()
            ->assertDontSee(route('admin.users.index'), false)
            ->assertDontSee(route('admin.tenants.index'), false)
            ->assertSee(route('admin.tenant-users.index'), false)
            ->assertSee('Tenant Locked');
    }

    public function test_tenant_user_is_restored_from_remember_me_cookie(): void
    {
        $tenant = $this->createTenant('tenant-remember-login');

        tenancy()->initialize($tenant);
        $user = User::query()->create([
            'name' => 'Remembered Tenant User',
            'username' => 'tenant-remember-user',
            'email' => 'tenant-remember@example.com',
            'phone' => '17776666',
            'password' => Hash::make('secret123'),
            'status' => 'Active',
        ]);
        $user->roles()->sync([$this->tenantRoleId($tenant, 'tenant-admin', false)]);
        tenancy()->end();
        DB::purge('tenant');

        $response = $this->post('/admin/login', [
            'login_scope' => 'tenant',
            'tenant_code' => $tenant->id,
            'username' => 'tenant-remember-user',
            'password' => 'secret123',
            'remember' => '1',
        ]);

        $response->assertRedirect('/home');

        $rememberCookie = collect($response->headers->getCookies())
            ->first(fn ($cookie) => $cookie->getName() === Auth::guard()->getRecallerName());

        $this->assertNotNull($rememberCookie, 'Expected the remember me cookie to be queued.');

        $this->flushSession();
        Auth::guard()->forgetUser();
        app('auth')->forgetGuards();

        $this->withUnencryptedCookie($rememberCookie->getName(), $rememberCookie->getValue())
            ->get('/home')
            ->assertOk()
            ->assertSee('Tenant Locked');

        $this->assertAuthenticated();
        $this->assertSame('tenant', session('auth_user_scope'));
        $this->assertSame($tenant->id, session('auth_tenant_id'));
    }

    public function test_tenant_viewer_only_sees_allowed_menus_and_cannot_open_manage_pages(): void
    {
        $tenant = $this->createTenant('tenant-viewer-access');

        tenancy()->initialize($tenant);
        $user = User::query()->create([
            'name' => 'Tenant Viewer',
            'username' => 'tenant-viewer-login',
            'email' => 'tenant-viewer@example.com',
            'phone' => '18887777',
            'password' => Hash::make('secret123'),
            'status' => 'Active',
        ]);
        $user->roles()->sync([$this->tenantRoleId($tenant, 'tenant-viewer', false)]);
        Branch::query()->create([
            'name' => 'Read Only Branch',
            'code' => 'readonly-branch',
            'status' => 'Active',
        ]);
        tenancy()->end();
        DB::purge('tenant');

        $this->post('/admin/login', [
            'login_scope' => 'tenant',
            'tenant_code' => $tenant->id,
            'username' => 'tenant-viewer-login',
            'password' => 'secret123',
        ])->assertRedirect('/home');

        $this->get('/home')
            ->assertOk()
            ->assertSee('Master Data')
            ->assertSee(route('admin.tenant-users.index'), false)
            ->assertSee(route('admin.branches.index'), false)
            ->assertSee(route('admin.promotions.index'), false)
            ->assertSee(route('admin.activity-logs.index'), false)
            ->assertDontSee(route('admin.roles.index'), false);

        $this->withSession([
            'auth_user_scope' => 'tenant',
            'auth_tenant_id' => $tenant->id,
            'admin_selected_tenant_id' => $tenant->id,
        ])->get(route('admin.branches.index'))
            ->assertOk()
            ->assertDontSee('Create Branch')
            ->assertSee('View only');

        $this->withSession([
            'auth_user_scope' => 'tenant',
            'auth_tenant_id' => $tenant->id,
            'admin_selected_tenant_id' => $tenant->id,
        ])->get(route('admin.branches.create'))
            ->assertRedirect(route('home'));

        $this->withSession([
            'auth_user_scope' => 'tenant',
            'auth_tenant_id' => $tenant->id,
            'admin_selected_tenant_id' => $tenant->id,
        ])->get(route('admin.roles.index'))
            ->assertRedirect(route('home'));
    }

    public function test_authenticated_navbar_uses_uploaded_profile_image(): void
    {
        Storage::fake('user');

        $user = $this->createUser([
            'username' => 'avatar-user',
            'email' => 'avatar@example.com',
            'phone' => '52345678',
        ]);

        Storage::disk('user')->put('header-avatar.jpg', 'avatar');

        $profile = Gallery::query()->create([
            'gallarieable_type' => User::class,
            'gallarieable_id' => $user->id,
            'type' => 'thumbnail',
            'status' => 'Active',
            'name' => 'header-avatar.jpg',
        ]);

        $user->forceFill([
            'profile_id' => $profile->id,
        ])->save();

        $this->actingAs($user)
            ->get('/home')
            ->assertOk()
            ->assertSee(Storage::disk('user')->url('header-avatar.jpg'), false);
    }

    public function test_admin_web_login_accepts_phone(): void
    {
        $user = $this->createUser([
            'username' => 'admin-phone',
            'email' => 'phone@example.com',
            'phone' => '123456789',
        ]);

        $this->post('/admin/login', [
            'username' => '0123456789',
            'password' => 'secret123',
        ])->assertRedirect('/home');

        $this->assertAuthenticatedAs($user);
    }

    public function test_admin_web_login_rejects_invalid_credentials(): void
    {
        $this->createUser([
            'username' => 'wrong-password-user',
            'email' => 'wrong-password@example.com',
            'phone' => '32345678',
        ]);

        $this->from('/admin/login')
            ->post('/admin/login', [
                'username' => 'wrong-password-user',
                'password' => 'bad-password',
            ])->assertRedirect('/admin/login')
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_admin_web_login_rejects_inactive_user(): void
    {
        $this->createUser([
            'username' => 'inactive-user',
            'email' => 'inactive@example.com',
            'phone' => '42345678',
            'status' => 'Inactive',
        ]);

        $this->from('/admin/login')
            ->post('/admin/login', [
                'username' => 'inactive-user',
                'password' => 'secret123',
            ])->assertRedirect('/admin/login')
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_administrator_routes_are_available_without_tenant_selection(): void
    {
        $user = $this->createUser([
            'username' => 'master-data-user',
            'email' => 'master-data@example.com',
            'phone' => '82345678',
        ]);

        $this->actingAs($user)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('datatable-users', false);

        $this->actingAs($user)
            ->get(route('admin.tenants.index'))
            ->assertOk()
            ->assertSee('datatable-tenants', false);
    }

    public function test_dashboard_shows_clear_message_when_user_has_no_tenants(): void
    {
        $user = $this->createUser([
            'username' => 'no-tenant-user',
            'email' => 'no-tenant@example.com',
            'phone' => '92345678',
        ]);

        $this->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('No tenant assigned')
            ->assertSee('No active tenant is assigned to your account.')
            ->assertSee('Sign Out')
            ->assertDontSee('Choose Tenant');
    }

    public function test_admin_routes_are_available_after_tenant_selection(): void
    {
        $user = $this->createUser([
            'username' => 'tenant-ready-user',
            'email' => 'tenant-ready@example.com',
            'phone' => '82345679',
        ]);
        $tenant = $this->createTenant('alpha');
        $user->tenants()->sync([$tenant->id]);

        $this->actingAs($user)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->get(route('admin.items.index'))
            ->assertOk()
            ->assertSee('Items')
            ->assertSee('Master Data');

        $this->actingAs($user)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('datatable-users', false);
    }

    private function createUser(array $attributes): User
    {
        return User::query()->create(array_merge([
            'name' => 'Admin User',
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('secret123'),
            'phone' => '12345678',
            'status' => 'Active',
        ], $attributes));
    }

    private function createTenant(string $id): Tenant
    {
        $databaseName = 'tenant-' . $id . '.sqlite';
        $databasePath = database_path($databaseName);
        $fallbackDatabasePath = database_path($id);

        if (File::exists($databasePath)) {
            File::delete($databasePath);
        }

        if (File::exists($fallbackDatabasePath)) {
            File::delete($fallbackDatabasePath);
        }

        $this->tenantDatabasePaths[] = $databasePath;
        $this->tenantDatabasePaths[] = $fallbackDatabasePath;

        $tenant = Tenant::query()->create([
            'id' => $id,
            'db_connection' => 'sqlite',
            'db_port' => '',
            'db_name' => $databaseName,
            'db_host' => '',
            'db_username' => '',
            'db_password' => '',
            'status' => 'Active',
        ]);

        tenancy()->initialize($tenant);
        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => database_path('migrations/tenant'),
            '--realpath' => true,
            '--force' => true,
        ]);
        tenancy()->end();
        DB::purge('tenant');

        return $tenant;
    }

    private function tenantRoleId(Tenant $tenant, string $roleName, bool $resetTenancy = true): int
    {
        if (! tenant() || tenant('id') !== $tenant->id) {
            tenancy()->initialize($tenant);
        }

        $roleId = Role::query()->where('name', $roleName)->value('id');

        if ($resetTenancy) {
            tenancy()->end();
            DB::purge('tenant');
        }

        $this->assertNotNull($roleId, 'Expected tenant role [' . $roleName . '] to exist.');

        return (int) $roleId;
    }
}
