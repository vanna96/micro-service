<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Gallery;
use App\Models\Item;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Promotion;
use App\Models\PromotionItem;
use App\Models\RateIndexValue;
use App\Models\Role;
use App\Models\Slider;
use App\Models\Tenant;
use App\Models\UnitOfMeasure;
use App\Models\UomGroup;
use App\Models\UomGroupUnit;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Stancl\Tenancy\Events\TenantCreated;
use Stancl\Tenancy\Events\TenantDeleted;
use Tests\TestCase;

class AdminCrudManagementTest extends TestCase
{
    protected string $databasePath;

    protected array $tenantDatabasePaths = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = storage_path('framework/testing/admin-crud.sqlite');

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
        Storage::fake('user');
        Storage::fake('category');
        Storage::fake('item');
        Storage::fake('slider');
        Storage::fake('promotion');
        Storage::fake('customer');

        Event::fake([
            TenantCreated::class,
            TenantDeleted::class,
        ]);
    }

    protected function tearDown(): void
    {
        DB::disconnect('central');
        DB::disconnect('mysql');
        DB::purge('central');
        DB::purge('mysql');

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

    public function test_admin_can_crud_users(): void
    {
        $admin = $this->createUser([
            'username' => 'admin-root',
            'email' => 'root@example.com',
            'phone' => '99999999',
        ]);

        $tenant = Tenant::query()->create([
            'id' => 'alpha',
            'db_connection' => 'sqlite',
            'db_port' => '3306',
            'db_name' => 'alpha_db',
            'db_host' => '127.0.0.1',
            'db_username' => 'alpha_user',
            'db_password' => 'secret',
            'status' => 'Active',
        ]);
        $admin->tenants()->sync([$tenant->id]);

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('datatable-users', false)
            ->assertSee('dataTables.bootstrap4.min.css', false);

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->post(route('admin.users.store'), [
                'name' => 'John Manager',
                'username' => 'john-manager',
                'email' => 'john@example.com',
                'phone' => '0123456789',
                'password' => 'secret123',
                'password_confirmation' => 'secret123',
                'first_name' => 'John',
                'last_name' => 'Manager',
                'country_code' => '855',
                'gender' => 'Male',
                'status' => 'Active',
                'profile' => UploadedFile::fake()->image('john-profile.jpg'),
                'tenants' => [$tenant->id],
            ])
            ->assertRedirect(route('admin.users.index'));

        $user = User::query()->with('galleries')->where('username', 'john-manager')->firstOrFail();
        $this->assertNotNull($user->profile_id);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'John Manager',
            'phone' => '123456789',
        ], 'central');
        $this->assertDatabaseHas('galleries', [
            'id' => $user->profile_id,
            'gallarieable_type' => User::class,
            'gallarieable_id' => $user->id,
            'type' => 'thumbnail',
        ], 'central');
        Storage::disk('user')->assertExists($user->galleries->firstWhere('id', $user->profile_id)->name);

        $this->assertDatabaseHas('user_tenants', [
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
        ], 'central');

        $oldProfileId = $user->profile_id;
        $oldProfileName = $user->galleries->firstWhere('id', $oldProfileId)->name;

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->post(route('admin.users.update', $user), [
                'name' => 'John Director',
                'username' => 'john-manager',
                'email' => 'john@example.com',
                'phone' => '0987654321',
                'password' => '',
                'password_confirmation' => '',
                'first_name' => 'John',
                'last_name' => 'Director',
                'country_code' => '855',
                'gender' => 'Male',
                'status' => 'Inactive',
                'profile' => UploadedFile::fake()->image('john-profile-updated.png'),
                'tenants' => [$tenant->id],
                '_method' => 'PUT',
            ])
            ->assertRedirect(route('admin.users.index'));

        $user->refresh();
        $user->load('galleries');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'John Director',
            'status' => 'Inactive',
            'phone' => '987654321',
        ], 'central');
        $this->assertNotSame($oldProfileId, $user->profile_id);
        $this->assertDatabaseMissing('galleries', [
            'id' => $oldProfileId,
        ], 'central');
        Storage::disk('user')->assertMissing($oldProfileName);
        $newProfile = Gallery::query()->findOrFail($user->profile_id);
        Storage::disk('user')->assertExists($newProfile->name);

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->delete(route('admin.users.destroy', $user))
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseMissing('users', [
            'id' => $user->id,
        ], 'central');
        $this->assertDatabaseMissing('galleries', [
            'id' => $user->profile_id,
        ], 'central');
    }

    public function test_admin_can_crud_tenant_users(): void
    {
        $admin = $this->createUser([
            'username' => 'tenant-user-admin',
            'email' => 'tenant-user-admin@example.com',
            'phone' => '91919191',
        ]);

        $tenant = $this->createSqliteTenant('tenant-users');
        $admin->tenants()->sync([$tenant->id]);
        $tenantAdminRoleId = $this->tenantRoleId($tenant, 'tenant-admin');
        $tenantViewerRoleId = $this->tenantRoleId($tenant, 'tenant-viewer');

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->get(route('admin.tenant-users.index'))
            ->assertOk()
            ->assertSee('datatable-tenant-users', false)
            ->assertSee('Create User')
            ->assertSee('Showing users for tenant', false)
            ->assertSee('<strong>'.$tenant->db_name.'</strong>', false);

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->post(route('admin.tenant-users.store'), [
                'name' => 'Tenant Staff',
                'username' => 'tenant-staff',
                'email' => 'tenant-staff@example.com',
                'phone' => '011223344',
                'password' => 'secret123',
                'password_confirmation' => 'secret123',
                'first_name' => 'Tenant',
                'last_name' => 'Staff',
                'country_code' => '855',
                'gender' => 'Female',
                'status' => 'Active',
                'profile' => UploadedFile::fake()->image('tenant-staff.jpg'),
                'roles' => [$tenantAdminRoleId],
            ])
            ->assertRedirect(route('admin.tenant-users.index'));

        tenancy()->initialize($tenant);
        $user = User::query()->with(['galleries', 'roles'])->where('username', 'tenant-staff')->firstOrFail();
        $this->assertNotNull($user->profile_id);
        $this->assertSame([$tenantAdminRoleId], $user->roles->pluck('id')->all());

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Tenant Staff',
            'phone' => '11223344',
            'status' => 'Active',
        ], 'tenant');
        $this->assertDatabaseHas('galleries', [
            'id' => $user->profile_id,
            'gallarieable_type' => User::class,
            'gallarieable_id' => $user->id,
            'type' => 'thumbnail',
        ], 'tenant');
        Storage::disk('user')->assertExists($user->galleries->firstWhere('id', $user->profile_id)->name);

        $oldProfileId = $user->profile_id;
        $oldProfileName = $user->galleries->firstWhere('id', $oldProfileId)->name;
        tenancy()->end();
        DB::purge('tenant');

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->post(route('admin.tenant-users.update', ['tenant_user' => $user->id]), [
                'name' => 'Tenant Supervisor',
                'username' => 'tenant-staff',
                'email' => 'tenant-supervisor@example.com',
                'phone' => '099887766',
                'password' => '',
                'password_confirmation' => '',
                'first_name' => 'Tenant',
                'last_name' => 'Supervisor',
                'country_code' => '855',
                'gender' => 'Female',
                'status' => 'Inactive',
                'profile' => UploadedFile::fake()->image('tenant-supervisor.png'),
                'roles' => [$tenantViewerRoleId],
                '_method' => 'PUT',
            ])
            ->assertRedirect(route('admin.tenant-users.index'));

        tenancy()->initialize($tenant);
        $user->refresh();
        $user->load(['galleries', 'roles']);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Tenant Supervisor',
            'email' => 'tenant-supervisor@example.com',
            'phone' => '99887766',
            'status' => 'Inactive',
        ], 'tenant');
        $this->assertSame([$tenantViewerRoleId], $user->roles->pluck('id')->all());
        $this->assertNotSame($oldProfileId, $user->profile_id);
        $this->assertDatabaseMissing('galleries', [
            'id' => $oldProfileId,
        ], 'tenant');
        Storage::disk('user')->assertMissing($oldProfileName);
        $newProfile = Gallery::query()->findOrFail($user->profile_id);
        Storage::disk('user')->assertExists($newProfile->name);
        tenancy()->end();
        DB::purge('tenant');

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->delete(route('admin.tenant-users.destroy', ['tenant_user' => $user->id]))
            ->assertRedirect(route('admin.tenant-users.index'));

        tenancy()->initialize($tenant);
        $this->assertDatabaseMissing('users', [
            'id' => $user->id,
        ], 'tenant');
        $this->assertDatabaseMissing('galleries', [
            'id' => $user->profile_id,
        ], 'tenant');
        tenancy()->end();
        DB::purge('tenant');
    }

    public function test_tenant_users_are_loaded_from_the_selected_tenant(): void
    {
        $admin = $this->createUser([
            'username' => 'tenant-user-scope-admin',
            'email' => 'tenant-user-scope@example.com',
            'phone' => '92929292',
        ]);

        $tenantA = $this->createSqliteTenant('tenant-users-a');
        $tenantB = $this->createSqliteTenant('tenant-users-b');
        $admin->tenants()->sync([$tenantA->id, $tenantB->id]);

        tenancy()->initialize($tenantA);
        User::query()->create([
            'name' => 'Tenant A User',
            'username' => 'tenant-a-user',
            'password' => Hash::make('secret123'),
            'status' => 'Active',
        ]);
        tenancy()->end();
        DB::purge('tenant');

        tenancy()->initialize($tenantB);
        User::query()->create([
            'name' => 'Tenant B User',
            'username' => 'tenant-b-user',
            'password' => Hash::make('secret123'),
            'status' => 'Active',
        ]);
        tenancy()->end();
        DB::purge('tenant');

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenantA->id])
            ->get(route('admin.tenant-users.index'))
            ->assertOk()
            ->assertSee('Showing users for tenant', false)
            ->assertSee('<strong>'.$tenantA->id.'</strong>', false)
            ->assertSee('Tenant A User')
            ->assertDontSee('Tenant B User');

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenantB->id])
            ->get(route('admin.tenant-users.index'))
            ->assertOk()
            ->assertSee('Showing users for tenant', false)
            ->assertSee('<strong>'.$tenantB->id.'</strong>', false)
            ->assertSee('Tenant B User')
            ->assertDontSee('Tenant A User');
    }

    public function test_tenant_user_can_login_and_cannot_access_administrator_or_tenants(): void
    {
        $tenant = $this->createSqliteTenant('tenant-login');
        $username = 'tenant-login-user-'.uniqid();

        tenancy()->initialize($tenant);
        User::query()->create([
            'name' => 'Tenant Login User',
            'username' => $username,
            'email' => 'tenant-login@example.com',
            'phone' => '16667777',
            'password' => Hash::make('secret123'),
            'status' => 'Active',
        ]);
        tenancy()->end();
        DB::purge('tenant');

        $this->post(route('admin.login.store'), [
            'login_scope' => 'tenant',
            'tenant_id' => $tenant->id,
            'username' => $username,
            'password' => 'secret123',
        ])->assertRedirect(route('home'));

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee(route('admin.users.index'), false)
            ->assertDontSee(route('admin.tenants.index'), false)
            ->assertSee(route('admin.tenant-users.index'), false);

        $this->get(route('admin.tenant-users.index'))
            ->assertOk()
            ->assertSee('Showing users for tenant', false)
            ->assertSee('<strong>'.$tenant->id.'</strong>', false);

        $this->get(route('admin.users.index'))
            ->assertRedirect(route('home'));

        $this->get(route('admin.tenants.index'))
            ->assertRedirect(route('home'));
    }

    public function test_tenant_activity_logs_are_recorded_and_visible_in_admin(): void
    {
        $admin = $this->createUser([
            'username' => 'activity-admin',
            'email' => 'activity-admin@example.com',
            'phone' => '93939393',
        ]);

        $tenant = $this->createSqliteTenant('activity-log-tenant');
        $admin->tenants()->sync([$tenant->id]);

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->post(route('admin.branches.store'), [
                'code' => 'activity-branch',
                'name' => 'Activity Branch',
                'location' => 'Phnom Penh',
                'sort_order' => 1,
                'status' => 'Active',
            ])
            ->assertRedirect(route('admin.branches.index'));

        tenancy()->initialize($tenant);

        $activity = ActivityLog::query()
            ->where('log_name', 'branch')
            ->where('event', 'created')
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame(Branch::class, $activity->subject_type);
        $this->assertSame('Activity Branch', $activity->getExtraProperty('subject.label'));
        $this->assertSame('administrator', $activity->getExtraProperty('actor.scope'));
        $this->assertSame($admin->name, $activity->getExtraProperty('actor.name'));

        tenancy()->end();
        DB::purge('tenant');

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->get(route('admin.activity-logs.index'))
            ->assertOk()
            ->assertSee('Tenant Activity Logs')
            ->assertSee('Activity Branch')
            ->assertSee($admin->name);
    }

    public function test_admin_can_crud_tenants(): void
    {
        $admin = $this->createUser([
            'username' => 'tenant-admin',
            'email' => 'tenant-admin@example.com',
            'phone' => '88888888',
        ]);
        $selectedTenant = $this->createSqliteTenant('tenant-admin-selected');
        $admin->tenants()->sync([$selectedTenant->id]);

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $selectedTenant->id])
            ->get(route('admin.tenants.index'))
            ->assertOk()
            ->assertSee('datatable-tenants', false)
            ->assertSee('dataTables.bootstrap4.min.css', false);

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $selectedTenant->id])
            ->post(route('admin.tenants.store'), [
                'id' => 'beta',
                'db_connection' => 'mysql',
                'db_port' => '3306',
                'db_name' => 'beta_db',
                'db_host' => '127.0.0.1',
                'db_username' => 'beta_user',
                'db_password' => 'beta_secret',
                'status' => 'Active',
            ])
            ->assertRedirect(route('admin.tenants.index'));

        $tenant = Tenant::query()->findOrFail('beta');

        $this->assertDatabaseHas('tenants', [
            'id' => 'beta',
        ], 'central');
        $this->assertSame('beta_db', $tenant->db_name);

        $this->assertDatabaseHas('domains', [
            'tenant_id' => 'beta',
            'domain' => 'beta.'.env('TENANT_HOST', 'localhost'),
        ], 'central');

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $selectedTenant->id])
            ->put(route('admin.tenants.update', $tenant), [
                'db_connection' => 'mysql',
                'db_port' => '3307',
                'db_name' => 'beta_db_v2',
                'db_host' => '192.168.1.10',
                'db_username' => 'beta_user_v2',
                'db_password' => 'beta_secret_v2',
                'status' => 'Inactive',
            ])
            ->assertRedirect(route('admin.tenants.index'));

        $tenant->refresh();

        $this->assertDatabaseHas('tenants', [
            'id' => 'beta',
        ], 'central');
        $this->assertSame('beta_db_v2', $tenant->db_name);
        $this->assertSame('Inactive', $tenant->status);

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $selectedTenant->id])
            ->delete(route('admin.tenants.destroy', $tenant))
            ->assertRedirect(route('admin.tenants.index'));

        $this->assertDatabaseMissing('tenants', [
            'id' => 'beta',
        ], 'central');
    }

    public function test_admin_can_update_general_settings_for_selected_tenant(): void
    {
        $admin = $this->createUser([
            'username' => 'general-settings-admin',
            'email' => 'general-settings-admin@example.com',
            'phone' => '71717171',
        ]);

        $tenant = $this->createSqliteTenant('general-settings');
        tenancy()->initialize($tenant);
        Currency::query()->create([
            'code' => 'KHR',
            'name' => 'Khmer Riel',
            'decimal_places' => 0,
            'status' => 'Active',
        ]);
        Currency::query()->create([
            'code' => 'USD',
            'name' => 'US Dollar',
            'decimal_places' => 2,
            'status' => 'Active',
        ]);
        Currency::query()->create([
            'code' => 'EUR',
            'name' => 'Euro',
            'decimal_places' => 2,
            'status' => 'Inactive',
        ]);
        tenancy()->end();
        DB::purge('tenant');

        $tenant->forceFill([
            'branding' => [
                'theme' => 'classic',
            ],
            'general_settings' => [
                'currency' => 'KHR',
            ],
        ])->save();
        $admin->tenants()->sync([$tenant->id]);

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->get(route('admin.general-settings.index'))
            ->assertOk()
            ->assertSee('General tenant preferences')
            ->assertSee('Managing general settings for tenant', false)
            ->assertSee($tenant->db_name)
            ->assertSee('KHR - Khmer Riel')
            ->assertSee('USD - US Dollar')
            ->assertDontSee('EUR - Euro');

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->put(route('admin.general-settings.update'), [
                'store_name' => 'Phnom Penh Flagship',
                'contact_email' => 'hello@example.com',
                'contact_phone' => '012345678',
                'address' => 'No. 12, Street 2004, Phnom Penh',
                'currency' => 'USD',
                'timezone' => 'Asia/Phnom_Penh',
                'locale' => 'en',
                'receipt_footer' => 'Thank you for shopping with us.',
                'telegram_notifications_enabled' => '1',
                'telegram_bot_token' => '123456:ABC-DEF',
                'telegram_chat_id' => '-100987654321',
            ])
            ->assertRedirect(route('admin.general-settings.index'));

        $tenant->refresh();

        $this->assertSame('classic', data_get($tenant->branding, 'theme'));
        $this->assertSame('Phnom Penh Flagship', data_get($tenant->general_settings, 'store_name'));
        $this->assertSame('hello@example.com', data_get($tenant->general_settings, 'contact_email'));
        $this->assertSame('012345678', data_get($tenant->general_settings, 'contact_phone'));
        $this->assertSame('No. 12, Street 2004, Phnom Penh', data_get($tenant->general_settings, 'address'));
        $this->assertSame('USD', data_get($tenant->general_settings, 'currency'));
        $this->assertSame('Asia/Phnom_Penh', data_get($tenant->general_settings, 'timezone'));
        $this->assertSame('en', data_get($tenant->general_settings, 'locale'));
        $this->assertSame('Thank you for shopping with us.', data_get($tenant->general_settings, 'receipt_footer'));
        $this->assertTrue((bool) data_get($tenant->general_settings, 'telegram_notifications_enabled'));
        $this->assertSame('123456:ABC-DEF', data_get($tenant->general_settings, 'telegram_bot_token'));
        $this->assertSame('-100987654321', data_get($tenant->general_settings, 'telegram_chat_id'));
    }

    public function test_admin_can_send_test_telegram_notification(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            'https://api.telegram.org/bot123456:ABC-DEF/sendMessage' => \Illuminate\Support\Facades\Http::response(['ok' => true], 200),
        ]);

        $admin = $this->createUser([
            'username' => 'telegram-admin',
            'email' => 'telegram-admin@example.com',
            'phone' => '72727272',
        ]);

        $tenant = $this->createSqliteTenant('telegram-test-tenant');
        $admin->tenants()->sync([$tenant->id]);

        $response = $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->postJson(route('admin.general-settings.test-telegram'), [
                'telegram_bot_token' => '123456:ABC-DEF',
                'telegram_chat_id' => '-100987654321',
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_admin_can_view_and_update_dedicated_telegram_notifications_page(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            'https://api.telegram.org/botMY_BOT_TOKEN/sendMessage' => \Illuminate\Support\Facades\Http::response(['ok' => true], 200),
        ]);

        $admin = $this->createUser([
            'username' => 'telegram-view-admin',
            'email' => 'telegram-view-admin@example.com',
            'phone' => '73737373',
        ]);

        $tenant = $this->createSqliteTenant('telegram-view-tenant');
        $admin->tenants()->sync([$tenant->id]);

        // 1. Visit index page
        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->get(route('admin.telegram-notifications.index'))
            ->assertOk()
            ->assertSee('Order & POS Receipts', false)
            ->assertSee('System Error Logs')
            ->assertSee('Send Real Sample Order Receipt');

        // 2. Update credentials
        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->put(route('admin.telegram-notifications.update'), [
                'telegram_notifications_enabled' => '1',
                'telegram_bot_token' => 'MY_BOT_TOKEN',
                'telegram_chat_id' => '-1005555555555',
            ])
            ->assertRedirect(route('admin.telegram-notifications.index', ['tab' => 'orders']));

        $tenant->refresh();
        $this->assertTrue((bool) data_get($tenant->general_settings, 'telegram_notifications_enabled'));
        $this->assertSame('MY_BOT_TOKEN', data_get($tenant->general_settings, 'telegram_bot_token'));
        $this->assertSame('-1005555555555', data_get($tenant->general_settings, 'telegram_chat_id'));

        // 3. Send test notification via dedicated test endpoint
        $testResponse = $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->postJson(route('admin.telegram-notifications.test'), [
                'telegram_bot_token' => 'MY_BOT_TOKEN',
                'telegram_chat_id' => '-1005555555555',
            ]);

        $testResponse->assertOk()->assertJson(['success' => true]);
    }

    public function test_admin_can_update_and_test_error_log_telegram_settings(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            'https://api.telegram.org/botMY_ERR_TOKEN/sendMessage' => \Illuminate\Support\Facades\Http::response(['ok' => true], 200),
        ]);

        $admin = $this->createUser([
            'username' => 'error-log-admin',
            'email' => 'error-log-admin@example.com',
            'phone' => '73737374',
        ]);

        $tenant = $this->createSqliteTenant('error-log-tenant');
        $admin->tenants()->sync([$tenant->id]);

        // 1. Visit index page with tab=errors
        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->get(route('admin.telegram-notifications.index', ['tab' => 'errors']))
            ->assertOk()
            ->assertSee('System Error Log Notifications')
            ->assertSee('Send Real Sample Error Alert');

        // 2. Update error log credentials
        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->put(route('admin.telegram-notifications.update'), [
                'active_tab' => 'errors',
                'telegram_error_log_enabled' => '1',
                'telegram_error_log_bot_token' => 'MY_ERR_TOKEN',
                'telegram_error_log_chat_id' => '-1007777777777',
            ])
            ->assertRedirect(route('admin.telegram-notifications.index', ['tab' => 'errors']));

        $tenant->refresh();
        $this->assertTrue((bool) data_get($tenant->general_settings, 'telegram_error_log_enabled'));
        $this->assertSame('MY_ERR_TOKEN', data_get($tenant->general_settings, 'telegram_error_log_bot_token'));
        $this->assertSame('-1007777777777', data_get($tenant->general_settings, 'telegram_error_log_chat_id'));

        // 3. Send test sample error log via dedicated test endpoint
        $sampleResponse = $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->postJson(route('admin.telegram-notifications.test'), [
                'type' => 'error_sample',
                'telegram_error_log_bot_token' => 'MY_ERR_TOKEN',
                'telegram_error_log_chat_id' => '-1007777777777',
            ]);

        $sampleResponse->assertOk()->assertJson(['success' => true]);

        // 4. Send ping error log via dedicated test endpoint
        $pingResponse = $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->postJson(route('admin.telegram-notifications.test'), [
                'type' => 'error_ping',
                'telegram_error_log_bot_token' => 'MY_ERR_TOKEN',
                'telegram_error_log_chat_id' => '-1007777777777',
            ]);

        $pingResponse->assertOk()->assertJson(['success' => true]);
    }

    public function test_admin_can_send_sample_order_test_from_central_tenant_management(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            'https://api.telegram.org/botTENANT_BOT_TOKEN/sendMessage' => \Illuminate\Support\Facades\Http::response(['ok' => true], 200),
        ]);

        $admin = $this->createUser([
            'username' => 'tenant-central-admin',
            'email' => 'tenant-central-admin@example.com',
            'phone' => '74747474',
        ]);

        $tenant = $this->createSqliteTenant('tenant-central-t');

        $response = $this->actingAs($admin)
            ->postJson(route('admin.tenants.test-telegram'), [
                'tenant_id' => $tenant->id,
                'telegram_bot_token' => 'TENANT_BOT_TOKEN',
                'telegram_chat_id' => '-1008888888888',
                'type' => 'sample_order',
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_admin_can_crud_categories(): void
    {
        $admin = $this->createUser([
            'username' => 'category-admin',
            'email' => 'category-admin@example.com',
            'phone' => '77777777',
        ]);

        $tenant = $this->createSqliteTenant('catalog');
        $admin->tenants()->sync([$tenant->id]);

        tenancy()->initialize($tenant);
        $parent = Category::query()->create([
            'name' => 'Beverages',
            'foreign_name' => 'ភេសជ្ជៈ',
            'status' => 'Active',
        ]);
        tenancy()->end();
        DB::purge('tenant');

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->get(route('admin.categories.index'))
            ->assertOk()
            ->assertSee('datatable-categories', false)
            ->assertSee('Create Category')
            ->assertSee('Showing categories for tenant', false)
            ->assertSee('<strong>'.$tenant->id.'</strong>', false);

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->post(route('admin.categories.store'), [
                'name' => 'Coffee',
                'foreign_name' => 'កាហ្វេ',
                'parent_id' => $parent->id,
                'status' => 'Active',
                'image' => UploadedFile::fake()->image('coffee.jpg'),
            ])
            ->assertRedirect(route('admin.categories.index'));

        tenancy()->initialize($tenant);
        $category = Category::query()->with('galleries')->where('name', 'Coffee')->firstOrFail();
        $this->assertNotNull($category->image_id);

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'parent_id' => $parent->id,
            'foreign_name' => 'កាហ្វេ',
            'status' => 'Active',
        ], 'tenant');
        $this->assertDatabaseHas('galleries', [
            'id' => $category->image_id,
            'gallarieable_type' => Category::class,
            'gallarieable_id' => $category->id,
            'type' => 'thumbnail',
        ], 'tenant');
        Storage::disk('category')->assertExists($category->galleries->firstWhere('id', $category->image_id)->name);

        $oldImageId = $category->image_id;
        $oldImageName = $category->galleries->firstWhere('id', $oldImageId)->name;
        tenancy()->end();
        DB::purge('tenant');

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->post(route('admin.categories.update', ['category' => $category->id]), [
                'name' => 'Cold Brew',
                'foreign_name' => 'កាហ្វេទឹកត្រជាក់',
                'parent_id' => '',
                'status' => 'Inactive',
                'image' => UploadedFile::fake()->image('cold-brew.png'),
                '_method' => 'PUT',
            ])
            ->assertRedirect(route('admin.categories.index'));

        tenancy()->initialize($tenant);
        $category->refresh();
        $category->load('galleries');

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Cold Brew',
            'parent_id' => null,
            'status' => 'Inactive',
        ], 'tenant');
        $this->assertNotSame($oldImageId, $category->image_id);
        $this->assertDatabaseMissing('galleries', [
            'id' => $oldImageId,
        ], 'tenant');
        Storage::disk('category')->assertMissing($oldImageName);
        $newImage = Gallery::query()->findOrFail($category->image_id);
        Storage::disk('category')->assertExists($newImage->name);
        tenancy()->end();
        DB::purge('tenant');

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->delete(route('admin.categories.destroy', ['category' => $category->id]))
            ->assertRedirect(route('admin.categories.index'));

        tenancy()->initialize($tenant);
        $this->assertDatabaseMissing('categories', [
            'id' => $category->id,
        ], 'tenant');
        $this->assertDatabaseMissing('galleries', [
            'id' => $category->image_id,
        ], 'tenant');
        tenancy()->end();
        DB::purge('tenant');
    }

    public function test_categories_are_loaded_from_the_selected_tenant(): void
    {
        $admin = $this->createUser([
            'username' => 'category-scope-admin',
            'email' => 'category-scope@example.com',
            'phone' => '76767676',
        ]);

        $tenantA = $this->createSqliteTenant('catalog-a');
        $tenantB = $this->createSqliteTenant('catalog-b');
        $admin->tenants()->sync([$tenantA->id, $tenantB->id]);

        tenancy()->initialize($tenantA);
        Category::query()->create([
            'name' => 'Tenant A Category',
            'status' => 'Active',
        ]);
        tenancy()->end();
        DB::purge('tenant');

        tenancy()->initialize($tenantB);
        Category::query()->create([
            'name' => 'Tenant B Category',
            'status' => 'Active',
        ]);
        tenancy()->end();
        DB::purge('tenant');

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenantA->id])
            ->get(route('admin.categories.index'))
            ->assertOk()
            ->assertSee('Showing categories for tenant', false)
            ->assertSee('<strong>'.$tenantA->id.'</strong>', false)
            ->assertSee('Tenant A Category')
            ->assertDontSee('Tenant B Category');

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenantB->id])
            ->get(route('admin.categories.index'))
            ->assertOk()
            ->assertSee('Showing categories for tenant', false)
            ->assertSee('<strong>'.$tenantB->id.'</strong>', false)
            ->assertSee('Tenant B Category')
            ->assertDontSee('Tenant A Category');
    }

    public function test_admin_can_crud_items(): void
    {
        $admin = $this->createUser([
            'username' => 'item-admin',
            'email' => 'item-admin@example.com',
            'phone' => '75757575',
        ]);

        $tenant = $this->createSqliteTenant('item-catalog');
        $admin->tenants()->sync([$tenant->id]);

        tenancy()->initialize($tenant);
        $branch = Branch::query()->firstOrCreate(
            ['code' => 'kampot-flagship'],
            [
                'name' => 'Kampot Flagship',
                'location' => 'Kampot City',
                'status' => 'Active',
            ]
        );
        $priceList = PriceList::query()->firstOrCreate(
            ['code' => 'item-default-list'],
            [
                'name' => 'Item Default List',
                'is_default' => true,
                'status' => 'Active',
            ]
        );
        $category = Category::query()->create([
            'name' => 'Wedding',
            'foreign_name' => 'អាពាហ៍ពិពាហ៍',
            'status' => 'Active',
        ]);
        tenancy()->end();
        DB::purge('tenant');

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->get(route('admin.items.index'))
            ->assertOk()
            ->assertSee('datatable-items', false)
            ->assertSee('Create Item')
            ->assertSee('Showing items for tenant', false)
            ->assertSee('<strong>'.$tenant->id.'</strong>', false);

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->post(route('admin.items.store'), [
                'category_id' => $category->id,
                'branch_id' => $branch->id,
                'price_list_id' => $priceList->id,
                'sku' => 'SKU-001',
                'name' => 'Khmer Wedding',
                'foreign_name' => 'ឈុតអាពាហ៍ពិពាហ៍ខ្មែរ',
                'description' => 'Premium Khmer wedding look.',
                'price' => '280.00',
                'stock' => 4,
                'is_premium' => 1,
                'is_featured' => 1,
                'is_new_arrival' => 1,
                'is_try_on_enabled' => 1,
                'status' => 'Active',
                'image' => UploadedFile::fake()->image('khmer-wedding.jpg'),
                'gallery_images' => [
                    UploadedFile::fake()->image('khmer-wedding-gallery-1.jpg'),
                    UploadedFile::fake()->image('khmer-wedding-gallery-2.jpg'),
                ],
            ])
            ->assertRedirect(route('admin.items.index'));

        tenancy()->initialize($tenant);
        $item = Item::query()->with('galleries')->where('sku', 'SKU-001')->firstOrFail();
        $this->assertNotNull($item->image_id);

        $this->assertDatabaseHas('items', [
            'id' => $item->id,
            'category_id' => $category->id,
            'branch_id' => $branch->id,
            'price_list_id' => $priceList->id,
            'sku' => 'SKU-001',
            'branch_name' => 'Kampot Flagship',
            'stock' => 4,
            'review_count' => 0,
            'sort_order' => 0,
            'is_premium' => true,
            'status' => 'Active',
        ], 'tenant');
        $this->assertDatabaseHas('galleries', [
            'id' => $item->image_id,
            'gallarieable_type' => Item::class,
            'gallarieable_id' => $item->id,
            'type' => 'thumbnail',
        ], 'tenant');
        Storage::disk('item')->assertExists($item->galleries->firstWhere('id', $item->image_id)->name);
        $this->assertCount(3, $item->galleries);
        $galleryImages = $item->galleries->where('type', 'galleries')->values();
        $this->assertCount(2, $galleryImages);
        foreach ($galleryImages as $galleryImage) {
            Storage::disk('item')->assertExists($galleryImage->name);
        }

        $oldImageId = $item->image_id;
        $oldImageName = $item->galleries->firstWhere('id', $oldImageId)->name;
        tenancy()->end();
        DB::purge('tenant');

        tenancy()->initialize($tenant);
        $branch = Branch::query()->firstOrCreate(
            ['code' => 'phnom-penh-boutique'],
            [
                'name' => 'Phnom Penh Boutique',
                'location' => 'Phnom Penh',
                'status' => 'Active',
            ]
        );
        $priceList = PriceList::query()->firstOrCreate(
            ['code' => 'item-vip-list'],
            [
                'name' => 'Item VIP List',
                'status' => 'Active',
            ]
        );
        tenancy()->end();
        DB::purge('tenant');

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->post(route('admin.items.update', ['item' => $item->id]), [
                'category_id' => $category->id,
                'branch_id' => $branch->id,
                'price_list_id' => $priceList->id,
                'sku' => 'SKU-001',
                'name' => 'Khmer Wedding Deluxe',
                'foreign_name' => 'ឈុតអាពាហ៍ពិពាហ៍ខ្មែរប្រណិត',
                'description' => 'Updated catalog copy.',
                'price' => '300.00',
                'stock' => 2,
                'status' => 'Inactive',
                'image' => UploadedFile::fake()->image('khmer-wedding-deluxe.png'),
                'gallery_images' => [
                    UploadedFile::fake()->image('khmer-wedding-gallery-3.jpg'),
                ],
                '_method' => 'PUT',
            ])
            ->assertRedirect(route('admin.items.index'));

        tenancy()->initialize($tenant);
        $item->refresh();
        $item->load('galleries');

        $this->assertDatabaseHas('items', [
            'id' => $item->id,
            'name' => 'Khmer Wedding Deluxe',
            'branch_id' => $branch->id,
            'price_list_id' => $priceList->id,
            'branch_name' => 'Phnom Penh Boutique',
            'price' => 300,
            'stock' => 2,
            'review_count' => 0,
            'sort_order' => 0,
            'status' => 'Inactive',
            'is_premium' => false,
            'is_featured' => false,
            'is_new_arrival' => false,
        ], 'tenant');
        $this->assertNotSame($oldImageId, $item->image_id);
        $this->assertDatabaseMissing('galleries', [
            'id' => $oldImageId,
        ], 'tenant');
        Storage::disk('item')->assertMissing($oldImageName);
        $newImage = Gallery::query()->findOrFail($item->image_id);
        Storage::disk('item')->assertExists($newImage->name);
        $this->assertCount(4, $item->galleries);
        $galleryToDelete = $item->galleries->where('type', 'galleries')->first();
        $this->assertNotNull($galleryToDelete);
        Storage::disk('item')->assertExists($galleryToDelete->name);
        tenancy()->end();
        DB::purge('tenant');

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->delete(route('admin.items.gallery.destroy', ['item' => $item->id, 'gallery' => $galleryToDelete->id]))
            ->assertOk()
            ->assertJson(['success' => true]);

        tenancy()->initialize($tenant);
        $this->assertDatabaseMissing('galleries', [
            'id' => $galleryToDelete->id,
        ], 'tenant');
        Storage::disk('item')->assertMissing($galleryToDelete->name);
        tenancy()->end();
        DB::purge('tenant');

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->delete(route('admin.items.destroy', ['item' => $item->id]))
            ->assertRedirect(route('admin.items.index'));

        tenancy()->initialize($tenant);
        $this->assertDatabaseMissing('items', [
            'id' => $item->id,
        ], 'tenant');
        $this->assertDatabaseMissing('galleries', [
            'id' => $item->image_id,
        ], 'tenant');
        tenancy()->end();
        DB::purge('tenant');
    }

    public function test_admin_can_crud_customers(): void
    {
        $admin = $this->createUser([
            'username' => 'customer-admin',
            'email' => 'customer-admin@example.com',
            'phone' => '74747474',
        ]);

        $tenant = $this->createSqliteTenant('customer-directory');
        $admin->tenants()->sync([$tenant->id]);

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->get(route('admin.customers.index'))
            ->assertOk()
            ->assertSee('datatable-customers', false)
            ->assertSee('Create Customer')
            ->assertSee('Showing customers for tenant', false)
            ->assertSee('<strong>'.$tenant->db_name.'</strong>', false);

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->post(route('admin.customers.store'), [
                'code' => 'CUS-001',
                'name' => 'Walk In VIP',
                'email' => 'customer@example.com',
                'phone' => '012345678',
                'profile' => UploadedFile::fake()->image('customer-profile.png'),
                'address' => 'Street 2004, Phnom Penh',
                'notes' => 'Prefers weekend delivery.',
                'status' => 'Active',
            ])
            ->assertRedirect(route('admin.customers.index'));

        tenancy()->initialize($tenant);
        $customer = Customer::query()->with('galleries')->where('code', 'CUS-001')->firstOrFail();

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'Walk In VIP',
            'phone' => '12345678',
            'status' => 'Active',
        ], 'tenant');
        $this->assertNotNull($customer->profile_id);
        $this->assertDatabaseHas('galleries', [
            'id' => $customer->profile_id,
            'gallarieable_type' => Customer::class,
            'gallarieable_id' => $customer->id,
            'type' => 'thumbnail',
        ], 'tenant');
        Storage::disk('customer')->assertExists($customer->galleries->firstWhere('id', $customer->profile_id)->name);

        $oldProfileId = $customer->profile_id;
        $oldProfileName = $customer->galleries->firstWhere('id', $oldProfileId)->name;
        tenancy()->end();
        DB::purge('tenant');

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->post(route('admin.customers.update', ['customer' => $customer->id]), [
                'code' => 'CUS-001',
                'name' => 'Walk In Guest',
                'email' => 'guest@example.com',
                'phone' => '098765432',
                'profile' => UploadedFile::fake()->image('customer-profile-updated.png'),
                'address' => 'Street 271, Phnom Penh',
                'notes' => 'Updated note.',
                'status' => 'Inactive',
                '_method' => 'PUT',
            ])
            ->assertRedirect(route('admin.customers.index'));

        tenancy()->initialize($tenant);
        $customer->refresh();
        $customer->load('galleries');

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'Walk In Guest',
            'email' => 'guest@example.com',
            'phone' => '98765432',
            'status' => 'Inactive',
        ], 'tenant');
        $this->assertNotSame($oldProfileId, $customer->profile_id);
        $this->assertDatabaseMissing('galleries', [
            'id' => $oldProfileId,
        ], 'tenant');
        Storage::disk('customer')->assertMissing($oldProfileName);
        $newProfile = Gallery::query()->findOrFail($customer->profile_id);
        Storage::disk('customer')->assertExists($newProfile->name);
        tenancy()->end();
        DB::purge('tenant');

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->delete(route('admin.customers.destroy', ['customer' => $customer->id]))
            ->assertRedirect(route('admin.customers.index'));

        tenancy()->initialize($tenant);
        $this->assertDatabaseMissing('customers', [
            'id' => $customer->id,
        ], 'tenant');
        $this->assertDatabaseMissing('galleries', [
            'id' => $customer->profile_id,
        ], 'tenant');
        tenancy()->end();
        DB::purge('tenant');
    }

    public function test_admin_can_crud_sliders(): void
    {
        $admin = $this->createUser([
            'username' => 'slider-admin',
            'email' => 'slider-admin@example.com',
            'phone' => '74747474',
        ]);

        $tenant = $this->createSqliteTenant('slider-catalog');
        $admin->tenants()->sync([$tenant->id]);

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->get(route('admin.sliders.index'))
            ->assertOk()
            ->assertSee('datatable-sliders', false)
            ->assertSee('Create Slider')
            ->assertSee('Showing sliders for tenant', false)
            ->assertSee('<strong>'.$tenant->id.'</strong>', false);

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->post(route('admin.sliders.store'), [
                'title' => 'New Year Campaign',
                'subtitle' => 'Celebrate with launch offers',
                'placement' => 'Website',
                'target_url' => 'https://example.com/new-year',
                'sort_order' => 3,
                'status' => 'Active',
                'image' => UploadedFile::fake()->image('website-slider.jpg', 1600, 900),
            ])
            ->assertRedirect(route('admin.sliders.index'));

        tenancy()->initialize($tenant);
        $slider = Slider::query()->with('galleries')->where('title', 'New Year Campaign')->firstOrFail();
        $this->assertNotNull($slider->image_id);

        $this->assertDatabaseHas('sliders', [
            'id' => $slider->id,
            'placement' => 'Website',
            'target_url' => 'https://example.com/new-year',
            'sort_order' => 3,
            'status' => 'Active',
            'recommended_dimensions' => 'Recommended website banner ratio 16:9 or wider, for example 1600x900 or 1920x800.',
        ], 'tenant');
        $this->assertDatabaseHas('galleries', [
            'id' => $slider->image_id,
            'gallarieable_type' => Slider::class,
            'gallarieable_id' => $slider->id,
            'type' => 'thumbnail',
        ], 'tenant');
        Storage::disk('slider')->assertExists($slider->galleries->firstWhere('id', $slider->image_id)->name);

        $oldImageId = $slider->image_id;
        $oldImageName = $slider->galleries->firstWhere('id', $oldImageId)->name;
        tenancy()->end();
        DB::purge('tenant');

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->post(route('admin.sliders.update', ['slider' => $slider->id]), [
                'title' => 'Mobile Launch Campaign',
                'subtitle' => 'Mobile-first promotion',
                'placement' => 'Mobile',
                'target_url' => 'https://example.com/mobile-launch',
                'sort_order' => 1,
                'status' => 'Inactive',
                'image' => UploadedFile::fake()->image('mobile-slider.png', 1080, 1920),
                '_method' => 'PUT',
            ])
            ->assertRedirect(route('admin.sliders.index'));

        tenancy()->initialize($tenant);
        $slider->refresh();
        $slider->load('galleries');

        $this->assertDatabaseHas('sliders', [
            'id' => $slider->id,
            'title' => 'Mobile Launch Campaign',
            'placement' => 'Mobile',
            'target_url' => 'https://example.com/mobile-launch',
            'sort_order' => 1,
            'status' => 'Inactive',
            'recommended_dimensions' => 'Recommended mobile banner ratio 4:5 or 9:16, for example 1080x1350 or 1080x1920.',
        ], 'tenant');
        $this->assertNotSame($oldImageId, $slider->image_id);
        $this->assertDatabaseMissing('galleries', [
            'id' => $oldImageId,
        ], 'tenant');
        Storage::disk('slider')->assertMissing($oldImageName);
        $newImage = Gallery::query()->findOrFail($slider->image_id);
        Storage::disk('slider')->assertExists($newImage->name);
        tenancy()->end();
        DB::purge('tenant');

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->delete(route('admin.sliders.destroy', ['slider' => $slider->id]))
            ->assertRedirect(route('admin.sliders.index'));

        tenancy()->initialize($tenant);
        $this->assertDatabaseMissing('sliders', [
            'id' => $slider->id,
        ], 'tenant');
        $this->assertDatabaseMissing('galleries', [
            'id' => $slider->image_id,
        ], 'tenant');
        tenancy()->end();
        DB::purge('tenant');
    }

    public function test_sliders_are_loaded_from_the_selected_tenant(): void
    {
        $admin = $this->createUser([
            'username' => 'slider-scope-admin',
            'email' => 'slider-scope@example.com',
            'phone' => '73737373',
        ]);

        $tenantA = $this->createSqliteTenant('slider-a');
        $tenantB = $this->createSqliteTenant('slider-b');
        $admin->tenants()->sync([$tenantA->id, $tenantB->id]);

        tenancy()->initialize($tenantA);
        Slider::query()->create([
            'title' => 'Tenant A Slider',
            'placement' => 'Website',
            'recommended_dimensions' => 'Recommended website banner ratio 16:9 or wider, for example 1600x900 or 1920x800.',
            'status' => 'Active',
        ]);
        tenancy()->end();
        DB::purge('tenant');

        tenancy()->initialize($tenantB);
        Slider::query()->create([
            'title' => 'Tenant B Slider',
            'placement' => 'Mobile',
            'recommended_dimensions' => 'Recommended mobile banner ratio 4:5 or 9:16, for example 1080x1350 or 1080x1920.',
            'status' => 'Active',
        ]);
        tenancy()->end();
        DB::purge('tenant');

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenantA->id])
            ->get(route('admin.sliders.index'))
            ->assertOk()
            ->assertSee('Showing sliders for tenant', false)
            ->assertSee('<strong>'.$tenantA->id.'</strong>', false)
            ->assertSee('Tenant A Slider')
            ->assertDontSee('Tenant B Slider');

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenantB->id])
            ->get(route('admin.sliders.index'))
            ->assertOk()
            ->assertSee('Showing sliders for tenant', false)
            ->assertSee('<strong>'.$tenantB->id.'</strong>', false)
            ->assertSee('Tenant B Slider')
            ->assertDontSee('Tenant A Slider');
    }

    public function test_admin_can_crud_branches(): void
    {
        $admin = $this->createUser([
            'username' => 'branch-admin',
            'email' => 'branch-admin@example.com',
            'phone' => '65656565',
        ]);

        $tenant = $this->createSqliteTenant('branch-catalog');
        $admin->tenants()->sync([$tenant->id]);

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->get(route('admin.branches.index'))
            ->assertOk()
            ->assertSee('datatable-branches', false)
            ->assertSee('Create Branch')
            ->assertSee('Showing branches for tenant', false)
            ->assertSee('<strong>'.$tenant->id.'</strong>', false);

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->post(route('admin.branches.store'), [
                'code' => 'kampot-flagship',
                'name' => 'Kampot Flagship',
                'foreign_name' => 'សាខាកំពត',
                'location' => 'Kampot City',
                'sort_order' => 2,
                'status' => 'Active',
            ])
            ->assertRedirect(route('admin.branches.index'));

        tenancy()->initialize($tenant);
        $branch = Branch::query()->where('code', 'kampot-flagship')->firstOrFail();

        $this->assertDatabaseHas('branches', [
            'id' => $branch->id,
            'name' => 'Kampot Flagship',
            'location' => 'Kampot City',
            'sort_order' => 2,
            'status' => 'Active',
        ], 'tenant');
        tenancy()->end();
        DB::purge('tenant');

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->put(route('admin.branches.update', ['branch' => $branch->id]), [
                'code' => 'phnom-penh-boutique',
                'name' => 'Phnom Penh Boutique',
                'foreign_name' => 'សាខាភ្នំពេញ',
                'location' => 'Phnom Penh',
                'sort_order' => 1,
                'status' => 'Inactive',
            ])
            ->assertRedirect(route('admin.branches.index'));

        tenancy()->initialize($tenant);
        $branch->refresh();

        $this->assertDatabaseHas('branches', [
            'id' => $branch->id,
            'code' => 'phnom-penh-boutique',
            'name' => 'Phnom Penh Boutique',
            'location' => 'Phnom Penh',
            'sort_order' => 1,
            'status' => 'Inactive',
        ], 'tenant');
        tenancy()->end();
        DB::purge('tenant');

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->delete(route('admin.branches.destroy', ['branch' => $branch->id]))
            ->assertRedirect(route('admin.branches.index'));

        tenancy()->initialize($tenant);
        $this->assertDatabaseMissing('branches', [
            'id' => $branch->id,
        ], 'tenant');
        tenancy()->end();
        DB::purge('tenant');
    }

    public function test_admin_can_crud_currencies(): void
    {
        $admin = $this->createUser([
            'username' => 'currency-admin',
            'email' => 'currency-admin@example.com',
            'phone' => '64646464',
        ]);

        $tenant = $this->createSqliteTenant('currency-book');
        $admin->tenants()->sync([$tenant->id]);

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->get(route('admin.currencies.index'))
            ->assertOk()
            ->assertSee('datatable-currencies', false)
            ->assertSee('Create Currency')
            ->assertSee('Showing currencies for tenant', false)
            ->assertSee($tenant->db_name);

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->post(route('admin.currencies.store'), [
                'code' => 'usd',
                'name' => 'US Dollar',
                'symbol' => '$',
                'decimal_places' => 2,
                'sort_order' => 2,
                'status' => 'Active',
            ])
            ->assertRedirect(route('admin.currencies.index'));

        tenancy()->initialize($tenant);
        $currency = Currency::query()->where('code', 'USD')->firstOrFail();
        $this->assertSame('US Dollar', $currency->name);
        $this->assertSame('$', $currency->symbol);
        $this->assertSame(2, $currency->decimal_places);
        $this->assertSame(2, $currency->sort_order);
        tenancy()->end();
        DB::purge('tenant');

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->put(route('admin.currencies.update', ['currency' => $currency->id]), [
                'code' => 'usd',
                'name' => 'US Dollar Main',
                'symbol' => 'US$',
                'decimal_places' => 0,
                'sort_order' => 1,
                'status' => 'Inactive',
            ])
            ->assertRedirect(route('admin.currencies.index'));

        tenancy()->initialize($tenant);
        $currency->refresh();
        $this->assertSame('USD', $currency->code);
        $this->assertSame('US Dollar Main', $currency->name);
        $this->assertSame('US$', $currency->symbol);
        $this->assertSame(0, $currency->decimal_places);
        $this->assertSame(1, $currency->sort_order);
        $this->assertSame('Inactive', $currency->status);
        tenancy()->end();
        DB::purge('tenant');

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->delete(route('admin.currencies.destroy', ['currency' => $currency->id]))
            ->assertRedirect(route('admin.currencies.index'));

        tenancy()->initialize($tenant);
        $this->assertDatabaseMissing('currencies', [
            'id' => $currency->id,
        ], 'tenant');
        tenancy()->end();
        DB::purge('tenant');
    }

    public function test_admin_can_manage_rate_index_grid(): void
    {
        $admin = $this->createUser([
            'username' => 'rate-index-admin',
            'email' => 'rate-index-admin@example.com',
            'phone' => '63636363',
        ]);

        $tenant = $this->createSqliteTenant('rate-index-book');
        $admin->tenants()->sync([$tenant->id]);

        tenancy()->initialize($tenant);
        $baseCurrency = Currency::query()->create([
            'code' => 'USD',
            'name' => 'US Dollar',
            'sort_order' => 1,
            'decimal_places' => 2,
            'status' => 'Active',
        ]);
        $khr = Currency::query()->create([
            'code' => 'KHR',
            'name' => 'Khmer Riel',
            'sort_order' => 2,
            'decimal_places' => 0,
            'status' => 'Active',
        ]);
        $aud = Currency::query()->create([
            'code' => 'AUD',
            'name' => 'Australian Dollar',
            'sort_order' => 3,
            'decimal_places' => 2,
            'status' => 'Active',
        ]);
        Currency::query()->create([
            'code' => 'EUR',
            'name' => 'Euro',
            'sort_order' => 4,
            'decimal_places' => 2,
            'status' => 'Inactive',
        ]);
        tenancy()->end();
        DB::purge('tenant');

        $tenant->forceFill([
            'general_settings' => [
                'currency' => $baseCurrency->code,
            ],
        ])->save();

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->get(route('admin.rate-index.index', [
                'year' => 2026,
                'month' => 2,
            ]))
            ->assertOk()
            ->assertSee('Exchange Rates')
            ->assertDontSee('Exchange Rates and Indexes')
            ->assertSee('Exchange Rates')
            ->assertDontSee('Indexes')
            ->assertSee('USD')
            ->assertSee('KHR')
            ->assertSee('AUD')
            ->assertDontSee('EUR')
            ->assertSee('0 dp')
            ->assertSee('2 dp');

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->put(route('admin.rate-index.update'), [
                'year' => 2026,
                'month' => 2,
                'cells' => [
                    1 => [
                        $khr->id => '4100',
                        $aud->id => '2650.25',
                    ],
                    29 => [
                        $khr->id => '9999',
                    ],
                ],
            ])
            ->assertRedirect(route('admin.rate-index.index', [
                'year' => 2026,
                'month' => 2,
            ]));

        tenancy()->initialize($tenant);
        $exchangeRateValue = RateIndexValue::query()
            ->where('dataset_type', 'exchange_rate')
            ->where('year', 2026)
            ->where('month', 2)
            ->where('day', 1)
            ->where('currency_id', $khr->id)
            ->firstOrFail();
        $this->assertSame('4100.00000000', $exchangeRateValue->value);
        $this->assertDatabaseMissing('rate_index_values', [
            'dataset_type' => 'exchange_rate',
            'year' => 2026,
            'month' => 2,
            'day' => 29,
            'currency_id' => $khr->id,
        ], 'tenant');
        tenancy()->end();
        DB::purge('tenant');

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->put(route('admin.rate-index.update'), [
                'year' => 2026,
                'month' => 2,
                'cells' => [
                    1 => [
                        $khr->id => '',
                    ],
                    2 => [
                        $aud->id => '2650.25',
                    ],
                ],
            ])
            ->assertRedirect(route('admin.rate-index.index', [
                'year' => 2026,
                'month' => 2,
            ]));

        tenancy()->initialize($tenant);
        $this->assertDatabaseMissing('rate_index_values', [
            'dataset_type' => 'exchange_rate',
            'year' => 2026,
            'month' => 2,
            'day' => 1,
            'currency_id' => $khr->id,
        ], 'tenant');
        tenancy()->end();
        DB::purge('tenant');
    }

    public function test_rate_index_rejects_values_that_exceed_currency_decimal_places(): void
    {
        $admin = $this->createUser([
            'username' => 'rate-format-admin',
            'email' => 'rate-format-admin@example.com',
            'phone' => '62626262',
        ]);

        $tenant = $this->createSqliteTenant('rate-format-book');
        $admin->tenants()->sync([$tenant->id]);

        tenancy()->initialize($tenant);
        Currency::query()->create([
            'code' => 'USD',
            'name' => 'US Dollar',
            'decimal_places' => 2,
            'status' => 'Active',
        ]);
        $khr = Currency::query()->create([
            'code' => 'KHR',
            'name' => 'Khmer Riel',
            'decimal_places' => 0,
            'status' => 'Active',
        ]);
        tenancy()->end();
        DB::purge('tenant');

        $tenant->forceFill([
            'general_settings' => [
                'currency' => 'USD',
            ],
        ])->save();

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->from(route('admin.rate-index.index', [
                'year' => 2026,
                'month' => 3,
            ]))
            ->put(route('admin.rate-index.update'), [
                'year' => 2026,
                'month' => 3,
                'cells' => [
                    1 => [
                        $khr->id => '2.20',
                    ],
                ],
            ])
            ->assertSessionHasErrors([
                'cells.1.'.$khr->id,
            ]);
    }

    public function test_admin_can_crud_price_lists(): void
    {
        $admin = $this->createUser([
            'username' => 'price-list-admin',
            'email' => 'price-list-admin@example.com',
            'phone' => '45454545',
        ]);

        $tenant = $this->createSqliteTenant('price-book');
        $admin->tenants()->sync([$tenant->id]);
        $retailCode = 'retail-2026-'.uniqid();
        $retailName = 'Retail 2026 '.uniqid();
        $retailUpdatedName = $retailName.' Main';
        $vipCode = 'vip-2026-'.uniqid();
        $vipName = 'VIP 2026 '.uniqid();

        tenancy()->initialize($tenant);
        $itemOne = Item::query()->firstOrCreate(
            ['sku' => 'PL-001'],
            [
                'name' => 'Classic Khmer Dress',
                'price' => 120,
                'stock' => 5,
                'status' => 'Active',
            ]
        );
        $itemTwo = Item::query()->firstOrCreate(
            ['sku' => 'PL-002'],
            [
                'name' => 'Modern Silk Suit',
                'price' => 240,
                'stock' => 3,
                'status' => 'Active',
            ]
        );
        tenancy()->end();
        DB::purge('tenant');

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->get(route('admin.price-lists.index'))
            ->assertOk()
            ->assertSee('datatable-price-lists', false)
            ->assertSee('Create Price List')
            ->assertSee('Showing price lists for tenant', false)
            ->assertSee('<strong>'.$tenant->id.'</strong>', false);

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->post(route('admin.price-lists.store'), [
                'code' => $retailCode,
                'name' => $retailName,
                'description' => 'Default selling price list.',
                'header_pricing_method' => 'discount',
                'header_discount_percent' => 12,
                'is_default' => 1,
                'status' => 'Active',
            ])
            ->assertRedirect(route('admin.price-lists.index'));

        tenancy()->initialize($tenant);
        $retailPriceList = PriceList::query()->where('code', $retailCode)->firstOrFail();
        $this->assertDatabaseHas('price_lists', [
            'id' => $retailPriceList->id,
            'name' => $retailName,
            'header_pricing_method' => 'discount',
            'header_discount_percent' => 12,
            'is_default' => true,
            'status' => 'Active',
        ], 'tenant');
        tenancy()->end();
        DB::purge('tenant');

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->put(route('admin.price-lists.update', ['price_list' => $retailPriceList->id]), [
                'code' => $retailCode,
                'name' => $retailUpdatedName,
                'description' => 'Updated retail list.',
                'header_pricing_method' => 'fixed',
                'header_fixed_price' => '109.99',
                'is_default' => 1,
                'status' => 'Active',
            ])
            ->assertRedirect(route('admin.price-lists.edit', ['price_list' => $retailPriceList->id]));

        tenancy()->initialize($tenant);
        $retailPriceList->refresh();
        $this->assertDatabaseHas('price_lists', [
            'id' => $retailPriceList->id,
            'name' => $retailUpdatedName,
            'header_pricing_method' => 'fixed',
            'header_fixed_price' => 109.99,
            'header_discount_percent' => null,
            'is_default' => true,
        ], 'tenant');
        tenancy()->end();
        DB::purge('tenant');

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->post(route('admin.price-lists.store'), [
                'code' => $vipCode,
                'name' => $vipName,
                'description' => 'VIP override list.',
                'header_pricing_method' => 'discount',
                'header_discount_percent' => 20,
                'is_default' => 1,
                'status' => 'Active',
            ])
            ->assertRedirect(route('admin.price-lists.index'));

        tenancy()->initialize($tenant);
        $vipPriceList = PriceList::query()->where('code', $vipCode)->firstOrFail();
        $retailPriceList->refresh();

        $this->assertDatabaseHas('price_lists', [
            'id' => $vipPriceList->id,
            'header_pricing_method' => 'discount',
            'header_discount_percent' => 20,
            'is_default' => true,
        ], 'tenant');
        $this->assertDatabaseHas('price_lists', [
            'id' => $retailPriceList->id,
            'is_default' => false,
        ], 'tenant');
        tenancy()->end();
        DB::purge('tenant');

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->post(route('admin.price-lists.lines.store', ['price_list' => $vipPriceList->id]), [
                'item_id' => $itemOne->id,
                'pricing_method' => 'fixed',
                'fixed_price' => '99.99',
                'status' => 'Active',
            ])
            ->assertRedirect(route('admin.price-lists.edit', ['price_list' => $vipPriceList->id]));

        tenancy()->initialize($tenant);
        $fixedLine = PriceListItem::query()
            ->where('price_list_id', $vipPriceList->id)
            ->where('item_id', $itemOne->id)
            ->firstOrFail();

        $this->assertDatabaseHas('price_list_items', [
            'id' => $fixedLine->id,
            'pricing_method' => 'fixed',
            'fixed_price' => 99.99,
            'discount_percent' => null,
            'status' => 'Active',
        ], 'tenant');
        tenancy()->end();
        DB::purge('tenant');

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->from(route('admin.price-lists.edit', ['price_list' => $vipPriceList->id]))
            ->post(route('admin.price-lists.lines.store', ['price_list' => $vipPriceList->id]), [
                'item_id' => $itemTwo->id,
                'pricing_method' => 'fixed',
                'status' => 'Active',
            ])
            ->assertRedirect(route('admin.price-lists.edit', ['price_list' => $vipPriceList->id]))
            ->assertSessionHasErrors('fixed_price');

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->post(route('admin.price-lists.lines.store', ['price_list' => $vipPriceList->id]), [
                'item_id' => $itemTwo->id,
                'pricing_method' => 'discount',
                'discount_percent' => 20,
                'status' => 'Active',
            ])
            ->assertRedirect(route('admin.price-lists.edit', ['price_list' => $vipPriceList->id]));

        tenancy()->initialize($tenant);
        $discountLine = PriceListItem::query()
            ->where('price_list_id', $vipPriceList->id)
            ->where('item_id', $itemTwo->id)
            ->firstOrFail();

        $this->assertDatabaseHas('price_list_items', [
            'id' => $discountLine->id,
            'pricing_method' => 'discount',
            'fixed_price' => null,
            'discount_percent' => 20,
            'status' => 'Active',
        ], 'tenant');
        tenancy()->end();
        DB::purge('tenant');

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->put(route('admin.price-lists.lines.update', ['price_list' => $vipPriceList->id, 'line' => $fixedLine->id]), [
                'pricing_method' => 'discount',
                'discount_percent' => 15,
                'status' => 'Inactive',
            ])
            ->assertRedirect(route('admin.price-lists.edit', ['price_list' => $vipPriceList->id]));

        tenancy()->initialize($tenant);
        $fixedLine->refresh();
        $this->assertDatabaseHas('price_list_items', [
            'id' => $fixedLine->id,
            'pricing_method' => 'discount',
            'fixed_price' => null,
            'discount_percent' => 15,
            'status' => 'Inactive',
        ], 'tenant');
        tenancy()->end();
        DB::purge('tenant');

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->put(route('admin.price-lists.update', ['price_list' => $vipPriceList->id]), [
                'code' => $vipCode,
                'name' => $vipName,
                'description' => 'VIP override list.',
                'header_pricing_method' => 'fixed',
                'header_fixed_price' => '180.00',
                'is_default' => 1,
                'status' => 'Inactive',
            ])
            ->assertRedirect(route('admin.price-lists.edit', ['price_list' => $vipPriceList->id]));

        tenancy()->initialize($tenant);
        $vipPriceList->refresh();
        $this->assertDatabaseHas('price_lists', [
            'id' => $vipPriceList->id,
            'header_pricing_method' => 'fixed',
            'header_fixed_price' => 180,
            'status' => 'Inactive',
            'is_default' => false,
        ], 'tenant');
        tenancy()->end();
        DB::purge('tenant');

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->delete(route('admin.price-lists.lines.destroy', ['price_list' => $vipPriceList->id, 'line' => $discountLine->id]))
            ->assertRedirect(route('admin.price-lists.edit', ['price_list' => $vipPriceList->id]));

        tenancy()->initialize($tenant);
        $this->assertDatabaseMissing('price_list_items', [
            'id' => $discountLine->id,
        ], 'tenant');
        tenancy()->end();
        DB::purge('tenant');

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->delete(route('admin.price-lists.destroy', ['price_list' => $vipPriceList->id]))
            ->assertRedirect(route('admin.price-lists.index'));

        tenancy()->initialize($tenant);
        $this->assertDatabaseMissing('price_lists', [
            'id' => $vipPriceList->id,
        ], 'tenant');
        $this->assertDatabaseMissing('price_list_items', [
            'id' => $fixedLine->id,
        ], 'tenant');
        tenancy()->end();
        DB::purge('tenant');
    }

    public function test_admin_can_crud_multi_type_promotions(): void
    {
        $admin = $this->createUser([
            'username' => 'promotion-admin',
            'email' => 'promotion-admin@example.com',
            'phone' => '56565656',
        ]);

        $tenant = $this->createSqliteTenant('promotion-book');
        $admin->tenants()->sync([$tenant->id]);

        tenancy()->initialize($tenant);
        $itemOne = Item::query()->firstOrCreate(
            ['sku' => 'PR-'.uniqid()],
            [
                'name' => 'Gold Silk Dress',
                'price' => 180,
                'stock' => 8,
                'status' => 'Active',
            ]
        );
        $itemTwo = Item::query()->firstOrCreate(
            ['sku' => 'PR-'.uniqid()],
            [
                'name' => 'Silver Wedding Suit',
                'price' => 240,
                'stock' => 4,
                'status' => 'Active',
            ]
        );
        tenancy()->end();
        DB::purge('tenant');

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->get(route('admin.promotions.index'))
            ->assertOk()
            ->assertSee('Create Promotion')
            ->assertSee('Showing promotions for tenant', false);

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->from(route('admin.promotions.create'))
            ->post(route('admin.promotions.store'), [
                'code' => 'invalid-subtotal-'.uniqid(),
                'name' => 'Invalid Subtotal',
                'type' => Promotion::TYPE_SUBTOTAL_DISCOUNT,
                'description' => 'This should fail.',
                'start_at' => '2026-01-01 00:00:00',
                'end_at' => '2026-01-31 23:59:59',
                'status' => 'Active',
            ])
            ->assertRedirect(route('admin.promotions.create'))
            ->assertSessionHasErrors(['threshold_amount', 'reward_discount_percent']);

        $itemPriceCode = 'item-price-'.uniqid();
        $subtotalCode = 'subtotal-'.uniqid();
        $bogoCode = 'bogo-'.uniqid();

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->post(route('admin.promotions.store'), [
                'code' => $itemPriceCode,
                'name' => 'New Year Item Price',
                'image' => UploadedFile::fake()->image('item-price-promotion.jpg'),
                'type' => Promotion::TYPE_ITEM_PRICE,
                'description' => 'Item price campaign.',
                'start_at' => '2026-01-01 00:00:00',
                'end_at' => '2026-01-31 23:59:59',
                'status' => 'Active',
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->post(route('admin.promotions.store'), [
                'code' => $subtotalCode,
                'name' => 'Spend and Save',
                'type' => Promotion::TYPE_SUBTOTAL_DISCOUNT,
                'description' => 'Subtotal campaign.',
                'start_at' => '2026-01-01 00:00:00',
                'end_at' => '2026-01-31 23:59:59',
                'threshold_amount' => '50.00',
                'reward_discount_percent' => 10,
                'status' => 'Active',
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->post(route('admin.promotions.store'), [
                'code' => $bogoCode,
                'name' => 'Buy One Get One',
                'type' => Promotion::TYPE_BOGO,
                'description' => 'BOGO campaign.',
                'start_at' => '2026-01-01 00:00:00',
                'end_at' => '2026-01-31 23:59:59',
                'buy_quantity' => 1,
                'get_quantity' => 1,
                'status' => 'Active',
            ])
            ->assertRedirect();

        tenancy()->initialize($tenant);
        $itemPricePromotion = Promotion::query()->where('code', $itemPriceCode)->firstOrFail();
        $subtotalPromotion = Promotion::query()->where('code', $subtotalCode)->firstOrFail();
        $bogoPromotion = Promotion::query()->where('code', $bogoCode)->firstOrFail();

        $this->assertDatabaseHas('promotions', [
            'id' => $itemPricePromotion->id,
            'type' => Promotion::TYPE_ITEM_PRICE,
            'status' => 'Active',
        ], 'tenant');
        $this->assertNotNull($itemPricePromotion->image_id);
        $this->assertDatabaseHas('galleries', [
            'id' => $itemPricePromotion->image_id,
            'gallarieable_type' => Promotion::class,
            'gallarieable_id' => $itemPricePromotion->id,
            'type' => 'thumbnail',
        ], 'tenant');
        $promotionImage = Gallery::query()->findOrFail($itemPricePromotion->image_id);
        Storage::disk('promotion')->assertExists($promotionImage->name);
        $this->assertDatabaseHas('promotions', [
            'id' => $subtotalPromotion->id,
            'type' => Promotion::TYPE_SUBTOTAL_DISCOUNT,
            'threshold_amount' => 50,
            'reward_discount_percent' => 10,
        ], 'tenant');
        $this->assertDatabaseHas('promotions', [
            'id' => $bogoPromotion->id,
            'type' => Promotion::TYPE_BOGO,
            'buy_quantity' => 1,
            'get_quantity' => 1,
        ], 'tenant');
        tenancy()->end();
        DB::purge('tenant');

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->from(route('admin.promotions.edit', ['promotion' => $itemPricePromotion->id]))
            ->post(route('admin.promotions.lines.store', ['promotion' => $itemPricePromotion->id]), [
                'item_id' => $itemOne->id,
                'pricing_method' => 'fixed',
                'status' => 'Active',
            ])
            ->assertRedirect(route('admin.promotions.edit', ['promotion' => $itemPricePromotion->id]))
            ->assertSessionHasErrors('fixed_price');

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->post(route('admin.promotions.lines.store', ['promotion' => $itemPricePromotion->id]), [
                'item_id' => $itemOne->id,
                'pricing_method' => 'fixed',
                'fixed_price' => '149.99',
                'status' => 'Active',
            ])
            ->assertRedirect(route('admin.promotions.edit', ['promotion' => $itemPricePromotion->id]));

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->post(route('admin.promotions.lines.store', ['promotion' => $itemPricePromotion->id]), [
                'item_id' => $itemTwo->id,
                'pricing_method' => 'discount',
                'discount_percent' => 25,
                'status' => 'Active',
            ])
            ->assertRedirect(route('admin.promotions.edit', ['promotion' => $itemPricePromotion->id]));

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->post(route('admin.promotions.lines.store', ['promotion' => $bogoPromotion->id]), [
                'item_id' => $itemOne->id,
                'line_role' => Promotion::BOGO_ROLE_BUY,
                'status' => 'Active',
            ])
            ->assertRedirect(route('admin.promotions.edit', ['promotion' => $bogoPromotion->id]));

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->post(route('admin.promotions.lines.store', ['promotion' => $bogoPromotion->id]), [
                'item_id' => $itemTwo->id,
                'line_role' => Promotion::BOGO_ROLE_GET,
                'status' => 'Active',
            ])
            ->assertRedirect(route('admin.promotions.edit', ['promotion' => $bogoPromotion->id]));

        tenancy()->initialize($tenant);
        $fixedLine = PromotionItem::query()
            ->where('promotion_id', $itemPricePromotion->id)
            ->where('item_id', $itemOne->id)
            ->firstOrFail();
        $discountLine = PromotionItem::query()
            ->where('promotion_id', $itemPricePromotion->id)
            ->where('item_id', $itemTwo->id)
            ->firstOrFail();
        $bogoLine = PromotionItem::query()
            ->where('promotion_id', $bogoPromotion->id)
            ->where('item_id', $itemOne->id)
            ->where('line_role', Promotion::BOGO_ROLE_BUY)
            ->firstOrFail();
        $rewardLine = PromotionItem::query()
            ->where('promotion_id', $bogoPromotion->id)
            ->where('item_id', $itemTwo->id)
            ->where('line_role', Promotion::BOGO_ROLE_GET)
            ->firstOrFail();

        $this->assertDatabaseHas('promotion_items', [
            'id' => $fixedLine->id,
            'pricing_method' => 'fixed',
            'fixed_price' => 149.99,
            'discount_percent' => null,
        ], 'tenant');
        $this->assertDatabaseHas('promotion_items', [
            'id' => $discountLine->id,
            'pricing_method' => 'discount',
            'discount_percent' => 25,
        ], 'tenant');
        $this->assertDatabaseHas('promotion_items', [
            'id' => $bogoLine->id,
            'line_role' => Promotion::BOGO_ROLE_BUY,
            'pricing_method' => null,
            'fixed_price' => null,
            'discount_percent' => null,
        ], 'tenant');
        $this->assertDatabaseHas('promotion_items', [
            'id' => $rewardLine->id,
            'line_role' => Promotion::BOGO_ROLE_GET,
            'pricing_method' => null,
            'fixed_price' => null,
            'discount_percent' => null,
        ], 'tenant');
        tenancy()->end();
        DB::purge('tenant');

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->put(route('admin.promotions.update', ['promotion' => $subtotalPromotion->id]), [
                'code' => $subtotalCode,
                'name' => 'Spend and Save Updated',
                'type' => Promotion::TYPE_SUBTOTAL_DISCOUNT,
                'description' => 'Updated subtotal campaign.',
                'start_at' => '2026-01-05 00:00:00',
                'end_at' => '2026-02-05 23:59:59',
                'threshold_amount' => '80.00',
                'reward_discount_percent' => 15,
                'status' => 'Inactive',
            ])
            ->assertRedirect(route('admin.promotions.edit', ['promotion' => $subtotalPromotion->id]));

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->put(route('admin.promotions.lines.update', ['promotion' => $itemPricePromotion->id, 'line' => $fixedLine->id]), [
                'pricing_method' => 'discount',
                'discount_percent' => 15,
                'status' => 'Inactive',
            ])
            ->assertRedirect(route('admin.promotions.edit', ['promotion' => $itemPricePromotion->id]));

        tenancy()->initialize($tenant);
        $subtotalPromotion->refresh();
        $fixedLine->refresh();
        $this->assertDatabaseHas('promotions', [
            'id' => $subtotalPromotion->id,
            'threshold_amount' => 80,
            'reward_discount_percent' => 15,
            'status' => 'Inactive',
        ], 'tenant');
        $this->assertDatabaseHas('promotion_items', [
            'id' => $fixedLine->id,
            'pricing_method' => 'discount',
            'discount_percent' => 15,
            'status' => 'Inactive',
        ], 'tenant');
        tenancy()->end();
        DB::purge('tenant');

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->delete(route('admin.promotions.lines.destroy', ['promotion' => $itemPricePromotion->id, 'line' => $discountLine->id]))
            ->assertRedirect(route('admin.promotions.edit', ['promotion' => $itemPricePromotion->id]));

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->delete(route('admin.promotions.destroy', ['promotion' => $bogoPromotion->id]))
            ->assertRedirect(route('admin.promotions.index'));

        tenancy()->initialize($tenant);
        $this->assertDatabaseMissing('promotion_items', [
            'id' => $discountLine->id,
        ], 'tenant');
        $this->assertDatabaseMissing('promotions', [
            'id' => $bogoPromotion->id,
        ], 'tenant');
        $this->assertDatabaseMissing('promotion_items', [
            'id' => $bogoLine->id,
        ], 'tenant');
        $this->assertDatabaseMissing('promotion_items', [
            'id' => $rewardLine->id,
        ], 'tenant');
        tenancy()->end();
        DB::purge('tenant');
    }

    public function test_cart_pricing_api_supports_item_subtotal_and_bogo_promotions(): void
    {
        $admin = $this->createUser([
            'username' => 'promotion-api-admin',
            'email' => 'promotion-api-admin@example.com',
            'phone' => '57575757',
        ]);

        $tenant = $this->createSqliteTenant('promotion-api');
        $admin->tenants()->sync([$tenant->id]);

        tenancy()->initialize($tenant);
        $fixedItem = Item::query()->create([
            'sku' => 'FIXED-'.uniqid(),
            'name' => 'Fixed Promo Item',
            'price' => 100,
            'stock' => 5,
            'status' => 'Active',
        ]);
        $discountItem = Item::query()->create([
            'sku' => 'DISC-'.uniqid(),
            'name' => 'Discount Promo Item',
            'price' => 200,
            'stock' => 7,
            'status' => 'Active',
        ]);
        $subtotalItem = Item::query()->create([
            'sku' => 'SUB-'.uniqid(),
            'name' => 'Subtotal Item',
            'price' => 30,
            'stock' => 20,
            'status' => 'Active',
        ]);
        $bogoItem = Item::query()->create([
            'sku' => 'BOGO-'.uniqid(),
            'name' => 'Bogo Buy Item',
            'price' => 15,
            'stock' => 20,
            'status' => 'Active',
        ]);
        $bogoRewardItem = Item::query()->create([
            'sku' => 'BOGO-REWARD-'.uniqid(),
            'name' => 'Bogo Reward Item',
            'price' => 10,
            'stock' => 20,
            'status' => 'Active',
        ]);
        $fallbackItem = Item::query()->create([
            'sku' => 'FALLBACK-'.uniqid(),
            'name' => 'Fallback Item',
            'price' => 40,
            'stock' => 10,
            'status' => 'Active',
        ]);

        $fixedPromotion = Promotion::query()->create([
            'code' => 'fixed-'.uniqid(),
            'name' => 'Fixed Promotion',
            'type' => Promotion::TYPE_ITEM_PRICE,
            'description' => 'Fixed price campaign.',
            'start_at' => now()->subDay(),
            'end_at' => now()->addDays(2),
            'status' => 'Active',
        ]);
        $discountPromotion = Promotion::query()->create([
            'code' => 'percent-'.uniqid(),
            'name' => 'Percent Promotion',
            'type' => Promotion::TYPE_ITEM_PRICE,
            'description' => 'Percent campaign.',
            'start_at' => now()->subHours(12),
            'end_at' => now()->addDays(2),
            'status' => 'Active',
        ]);
        $subtotalPromotion = Promotion::query()->create([
            'code' => 'subtotal-'.uniqid(),
            'name' => 'Subtotal Promotion',
            'type' => Promotion::TYPE_SUBTOTAL_DISCOUNT,
            'description' => 'Spend and save.',
            'start_at' => now()->subDay(),
            'end_at' => now()->addDays(2),
            'threshold_amount' => 50,
            'reward_discount_percent' => 10,
            'status' => 'Active',
        ]);
        $bogoPromotion = Promotion::query()->create([
            'code' => 'bogo-'.uniqid(),
            'name' => 'Bogo Promotion',
            'type' => Promotion::TYPE_BOGO,
            'description' => 'Buy one get one.',
            'start_at' => now()->subDay(),
            'end_at' => now()->addDays(2),
            'buy_quantity' => 1,
            'get_quantity' => 1,
            'status' => 'Active',
        ]);
        $expiredPromotion = Promotion::query()->create([
            'code' => 'expired-'.uniqid(),
            'name' => 'Expired Promotion',
            'type' => Promotion::TYPE_ITEM_PRICE,
            'description' => 'Expired.',
            'start_at' => now()->subDays(5),
            'end_at' => now()->subDays(2),
            'status' => 'Active',
        ]);
        $inactivePromotion = Promotion::query()->create([
            'code' => 'inactive-'.uniqid(),
            'name' => 'Inactive Promotion',
            'type' => Promotion::TYPE_ITEM_PRICE,
            'description' => 'Inactive.',
            'start_at' => now()->subDay(),
            'end_at' => now()->addDay(),
            'status' => 'Inactive',
        ]);

        $fixedPromotion->lines()->create([
            'item_id' => $fixedItem->id,
            'pricing_method' => 'fixed',
            'fixed_price' => 70,
            'status' => 'Active',
        ]);
        $discountPromotion->lines()->create([
            'item_id' => $discountItem->id,
            'pricing_method' => 'discount',
            'discount_percent' => 25,
            'status' => 'Active',
        ]);
        $bogoPromotion->lines()->create([
            'item_id' => $bogoItem->id,
            'line_role' => Promotion::BOGO_ROLE_BUY,
            'status' => 'Active',
        ]);
        $bogoPromotion->lines()->create([
            'item_id' => $bogoRewardItem->id,
            'line_role' => Promotion::BOGO_ROLE_GET,
            'status' => 'Active',
        ]);
        $expiredPromotion->lines()->create([
            'item_id' => $fallbackItem->id,
            'pricing_method' => 'discount',
            'discount_percent' => 50,
            'status' => 'Active',
        ]);
        $inactivePromotion->lines()->create([
            'item_id' => $fallbackItem->id,
            'pricing_method' => 'fixed',
            'fixed_price' => 5,
            'status' => 'Active',
        ]);
        tenancy()->end();
        DB::purge('tenant');

        $token = $admin->createToken('promotion-api')->plainTextToken;

        $fixedResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Tenant' => $tenant->id,
            'Accept' => 'application/json',
        ])->postJson('http://localhost/v1/api/cart/price', [
            'items' => [
                ['item_id' => $fixedItem->id, 'quantity' => 1],
            ],
        ]);
        $fixedResponse->assertOk()
            ->assertJsonPath('applied_promotion.type', Promotion::TYPE_ITEM_PRICE);
        $this->assertSame(30.0, (float) $fixedResponse->json('discount_total'));
        $this->assertSame(70.0, (float) $fixedResponse->json('final_total'));

        $discountResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Tenant' => $tenant->id,
            'Accept' => 'application/json',
        ])->postJson('http://localhost/v1/api/cart/price', [
            'items' => [
                ['item_id' => $discountItem->id, 'quantity' => 1],
            ],
        ]);
        $discountResponse->assertOk()
            ->assertJsonPath('applied_promotion.code', $discountPromotion->code);
        $this->assertSame(50.0, (float) $discountResponse->json('discount_total'));
        $this->assertSame(150.0, (float) $discountResponse->json('final_total'));

        $subtotalResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Tenant' => $tenant->id,
            'Accept' => 'application/json',
        ])->postJson('http://localhost/v1/api/cart/price', [
            'items' => [
                ['item_id' => $subtotalItem->id, 'quantity' => 2],
            ],
        ]);
        $subtotalResponse->assertOk()
            ->assertJsonPath('applied_promotion.type', Promotion::TYPE_SUBTOTAL_DISCOUNT);
        $this->assertSame(6.0, (float) $subtotalResponse->json('discount_total'));
        $this->assertSame(54.0, (float) $subtotalResponse->json('final_total'));

        $bogoResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Tenant' => $tenant->id,
            'Accept' => 'application/json',
        ])->postJson('http://localhost/v1/api/cart/price', [
            'items' => [
                ['item_id' => $bogoItem->id, 'quantity' => 1],
                ['item_id' => $bogoRewardItem->id, 'quantity' => 1],
            ],
        ]);
        $bogoResponse->assertOk()
            ->assertJsonPath('applied_promotion.type', Promotion::TYPE_BOGO);
        $this->assertSame(10.0, (float) $bogoResponse->json('discount_total'));
        $this->assertSame(15.0, (float) $bogoResponse->json('final_total'));

        $winnerResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Tenant' => $tenant->id,
            'Accept' => 'application/json',
        ])->postJson('http://localhost/v1/api/cart/price', [
            'items' => [
                ['item_id' => $fixedItem->id, 'quantity' => 1],
                ['item_id' => $subtotalItem->id, 'quantity' => 2],
            ],
        ]);
        $winnerResponse->assertOk()
            ->assertJsonPath('applied_promotion.code', $fixedPromotion->code);
        $this->assertSame(30.0, (float) $winnerResponse->json('discount_total'));
        $this->assertSame(130.0, (float) $winnerResponse->json('final_total'));

        $fallbackResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Tenant' => $tenant->id,
            'Accept' => 'application/json',
        ])->postJson('http://localhost/v1/api/cart/price', [
            'items' => [
                ['item_id' => $fallbackItem->id, 'quantity' => 1],
            ],
        ]);
        $fallbackResponse->assertOk()
            ->assertJsonPath('applied_promotion', null);
        $this->assertSame(0.0, (float) $fallbackResponse->json('discount_total'));
        $this->assertSame(40.0, (float) $fallbackResponse->json('final_total'));
    }

    public function test_item_api_keeps_base_prices_even_when_promotions_exist(): void
    {
        $admin = $this->createUser([
            'username' => 'promotion-item-admin',
            'email' => 'promotion-item-admin@example.com',
            'phone' => '58585858',
        ]);

        $tenant = $this->createSqliteTenant('promotion-item-list');
        $admin->tenants()->sync([$tenant->id]);
        $sku = 'ITEM-LIST-'.uniqid();

        tenancy()->initialize($tenant);
        $item = Item::query()->create([
            'sku' => $sku,
            'name' => 'Listed Item',
            'price' => 100,
            'stock' => 5,
            'status' => 'Active',
        ]);
        $promotion = Promotion::query()->create([
            'code' => 'item-list-'.uniqid(),
            'name' => 'List Promo',
            'type' => Promotion::TYPE_ITEM_PRICE,
            'description' => 'Should not change item list response.',
            'start_at' => now()->subDay(),
            'end_at' => now()->addDay(),
            'status' => 'Active',
        ]);
        $promotion->lines()->create([
            'item_id' => $item->id,
            'pricing_method' => 'fixed',
            'fixed_price' => 70,
            'status' => 'Active',
        ]);
        tenancy()->end();
        DB::purge('tenant');

        $token = $admin->createToken('promotion-item-list')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Tenant' => $tenant->id,
            'Accept' => 'application/json',
        ])->getJson('http://localhost/v1/api/item/list?per_page=20');

        $response->assertOk();

        $payload = collect($response->json('data'))->firstWhere('sku', $sku);

        $this->assertNotNull($payload);
        $this->assertSame(100.0, (float) $payload['price']);
        $this->assertArrayNotHasKey('discount_percent', $payload);
        $this->assertArrayNotHasKey('final_price', $payload);
    }

    public function test_item_uom_reduction_is_saved_and_used_for_cart_pricing(): void
    {
        $admin = $this->createUser([
            'username' => 'uom-price-admin',
            'email' => 'uom-price-admin@example.com',
            'phone' => '59595959',
        ]);
        $tenant = $this->createSqliteTenant('item-uom-pricing');
        $admin->tenants()->sync([$tenant->id]);

        tenancy()->initialize($tenant);
        $currency = Currency::query()->create([
            'code' => 'AUD',
            'name' => 'Australian Dollar',
            'symbol' => '$',
            'decimal_places' => 2,
            'status' => 'Active',
        ]);
        $pack = UnitOfMeasure::query()->create([
            'code' => 'PACK',
            'name' => 'Pack',
            'symbol' => 'Pack',
            'status' => 'Active',
        ]);
        $sixPack = UnitOfMeasure::query()->create([
            'code' => '6PACK',
            'name' => '6 Packs',
            'symbol' => '6Pack',
            'status' => 'Active',
        ]);
        $group = UomGroup::query()->create([
            'code' => 'PACKS',
            'name' => 'Pack Sizes',
            'base_unit_id' => $pack->id,
            'status' => 'Active',
        ]);
        UomGroupUnit::query()->create([
            'uom_group_id' => $group->id,
            'unit_of_measure_id' => $pack->id,
            'alternate_quantity' => 1,
            'base_quantity' => 1,
            'conversion_factor_to_base' => 1,
            'is_base_unit' => true,
            'status' => 'Active',
        ]);
        UomGroupUnit::query()->create([
            'uom_group_id' => $group->id,
            'unit_of_measure_id' => $sixPack->id,
            'alternate_quantity' => 1,
            'base_quantity' => 6,
            'conversion_factor_to_base' => 6,
            'is_base_unit' => false,
            'status' => 'Active',
        ]);
        tenancy()->end();
        DB::purge('tenant');

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->get(route('admin.items.create'))
            ->assertOk()
            ->assertSee('UoM Pricing')
            ->assertSee('Reduce By %')
            ->assertSee('id="item-uom-pricing-trigger"', false)
            ->assertSee('data-bs-target="#item-uom-pricing-modal"', false)
            ->assertSee('id="item-uom-pricing-modal"', false);

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->post(route('admin.items.store'), [
                'item_type' => 'uom',
                'uom_group_id' => $group->id,
                'currency_id' => $currency->id,
                'sku' => 'UOM-PRICE-001',
                'name' => 'SAP UoM Pricing Item',
                'price' => 22,
                'stock' => 20,
                'status' => 'Active',
                'uom_prices' => [
                    [
                        'unit_of_measure_id' => $pack->id,
                        'reduce_by_percent' => 0,
                        'price' => 22,
                        'is_auto' => 1,
                    ],
                    [
                        'unit_of_measure_id' => $sixPack->id,
                        'reduce_by_percent' => 5,
                        'price' => 125.40,
                        'is_auto' => 1,
                    ],
                ],
            ])
            ->assertRedirect(route('admin.items.index'));

        tenancy()->initialize($tenant);
        $item = Item::query()->where('sku', 'UOM-PRICE-001')->firstOrFail();
        $this->assertDatabaseHas('item_uom_prices', [
            'item_id' => $item->id,
            'unit_of_measure_id' => $sixPack->id,
            'reduce_by_percent' => 5,
            'price' => null,
            'is_auto' => true,
        ], 'tenant');
        tenancy()->end();
        DB::purge('tenant');

        $token = $admin->createToken('item-uom-pricing')->plainTextToken;
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Tenant' => $tenant->id,
            'Accept' => 'application/json',
        ])->postJson('http://localhost/v1/api/cart/price', [
            'items' => [[
                'item_id' => $item->id,
                'uom_id' => $sixPack->id,
                'quantity' => 1,
            ]],
        ]);

        $response->assertOk();
        $this->assertSame(125.4, (float) $response->json('items.0.unit_price'));
        $this->assertSame(125.4, (float) $response->json('subtotal'));
    }

    public function test_item_uom_active_status_controls_pos_visibility_and_ordering(): void
    {
        $admin = $this->createUser([
            'username' => 'uom-active-admin',
            'email' => 'uom-active-admin@example.com',
            'phone' => '58585858',
        ]);
        $tenant = $this->createSqliteTenant('item-uom-active');
        $admin->tenants()->sync([$tenant->id]);

        tenancy()->initialize($tenant);
        $currency = Currency::query()->create([
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'decimal_places' => 2,
            'status' => 'Active',
        ]);
        $ea = UnitOfMeasure::query()->create([
            'code' => 'EA',
            'name' => 'Each',
            'symbol' => 'ea',
            'status' => 'Active',
        ]);
        $box = UnitOfMeasure::query()->create([
            'code' => 'BOX',
            'name' => 'Box',
            'symbol' => 'bx',
            'status' => 'Active',
        ]);
        $group = UomGroup::query()->create([
            'code' => 'EA-BOX',
            'name' => 'Each and Box',
            'base_unit_id' => $ea->id,
            'status' => 'Active',
        ]);
        UomGroupUnit::query()->create([
            'uom_group_id' => $group->id,
            'unit_of_measure_id' => $ea->id,
            'alternate_quantity' => 1,
            'base_quantity' => 1,
            'conversion_factor_to_base' => 1,
            'is_base_unit' => true,
            'status' => 'Active',
        ]);
        UomGroupUnit::query()->create([
            'uom_group_id' => $group->id,
            'unit_of_measure_id' => $box->id,
            'alternate_quantity' => 1,
            'base_quantity' => 12,
            'conversion_factor_to_base' => 12,
            'is_base_unit' => false,
            'status' => 'Active',
        ]);
        tenancy()->end();
        DB::purge('tenant');

        // 1. Validation prevents deactivating the base unit
        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->from(route('admin.items.create'))
            ->post(route('admin.items.store'), [
                'item_type' => 'uom',
                'uom_group_id' => $group->id,
                'currency_id' => $currency->id,
                'sku' => 'UOM-INACTIVE-BASE',
                'name' => 'Base Inactive Test',
                'price' => 10,
                'stock' => 10,
                'status' => 'Active',
                'uom_prices' => [
                    [
                        'unit_of_measure_id' => $ea->id,
                        'is_auto' => 1,
                        'is_active' => 0,
                    ],
                ],
            ])
            ->assertSessionHasErrors('uom_prices.0.is_active');

        // 2. Successfully save item with alternate unit inactive
        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->post(route('admin.items.store'), [
                'item_type' => 'uom',
                'uom_group_id' => $group->id,
                'currency_id' => $currency->id,
                'sku' => 'UOM-ACTIVE-TEST-001',
                'name' => 'Active Status UoM Item',
                'price' => 20,
                'stock' => 50,
                'status' => 'Active',
                'uom_prices' => [
                    [
                        'unit_of_measure_id' => $ea->id,
                        'reduce_by_percent' => 0,
                        'price' => 20,
                        'is_auto' => 1,
                        'is_active' => 1,
                    ],
                    [
                        'unit_of_measure_id' => $box->id,
                        'reduce_by_percent' => 10,
                        'price' => 216,
                        'is_auto' => 1,
                        'is_active' => 0,
                    ],
                ],
            ])
            ->assertRedirect(route('admin.items.index'));

        // 3. Database assertion: BOX is inactive
        tenancy()->initialize($tenant);
        $item = Item::query()
            ->with([
                'currency',
                'uomGroup.units.unit',
                'uomPrices',
            ])
            ->where('sku', 'UOM-ACTIVE-TEST-001')
            ->firstOrFail();
        $this->assertDatabaseHas('item_uom_prices', [
            'item_id' => $item->id,
            'unit_of_measure_id' => $box->id,
            'is_active' => false,
        ], 'tenant');

        // 4. Mobile payload excludes inactive unit
        $controller = app(\App\Http\Controllers\API\V1\Mobile\CatalogController::class);
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('mobileItemPayload');
        $method->setAccessible(true);
        $payload = $method->invoke($controller, $item);

        $unitIds = collect($payload['uom_group']['units'])->pluck('id')->all();
        $this->assertContains($ea->id, $unitIds);
        $this->assertNotContains($box->id, $unitIds);
        $this->assertCount(1, $payload['uom_group']['units']);
        tenancy()->end();
        DB::purge('tenant');

        // 5. Ordering inactive unit is blocked
        $token = $admin->createToken('inactive-uom-test')->plainTextToken;
        $blockedResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Tenant' => $tenant->id,
            'Accept' => 'application/json',
        ])->postJson('http://localhost/v1/api/cart/price', [
            'items' => [[
                'item_id' => $item->id,
                'uom_id' => $box->id,
                'quantity' => 1,
            ]],
        ]);
        $blockedResponse->assertStatus(422)
            ->assertJsonValidationErrors('uom_id');

        // 6. Ordering active unit succeeds
        $allowedResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Tenant' => $tenant->id,
            'Accept' => 'application/json',
        ])->postJson('http://localhost/v1/api/cart/price', [
            'items' => [[
                'item_id' => $item->id,
                'uom_id' => $ea->id,
                'quantity' => 2,
            ]],
        ]);
        $allowedResponse->assertOk();
        $this->assertSame(20.0, (float) $allowedResponse->json('items.0.unit_price'));
        $this->assertSame(40.0, (float) $allowedResponse->json('subtotal'));
    }

    public function test_branch_and_category_api_support_crud_without_localhost_domain(): void
    {
        $admin = $this->createUser([
            'username' => 'tenant-api-admin',
            'email' => 'tenant-api-admin@example.com',
            'phone' => '57575757',
        ]);

        $tenant = $this->createSqliteTenant('tenant-api-crud');
        $admin->tenants()->sync([$tenant->id]);
        $token = $admin->createToken('tenant-api-crud')->plainTextToken;

        $branchResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Tenant' => $tenant->id,
            'Accept' => 'application/json',
        ])->postJson('/v1/api/branch/store', [
            'code' => 'api-branch',
            'name' => 'API Branch',
            'location' => 'Phnom Penh',
            'status' => 'Active',
        ]);

        $branchResponse->assertOk()
            ->assertJsonPath('data.code', 'api-branch');

        $branchId = $branchResponse->json('data.id');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Tenant' => $tenant->id,
            'Accept' => 'application/json',
        ])->getJson('/v1/api/branch/edit/'.$branchId)
            ->assertOk()
            ->assertJsonPath('data.name', 'API Branch');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Tenant' => $tenant->id,
            'Accept' => 'application/json',
        ])->patchJson('/v1/api/branch/update/'.$branchId, [
            'name' => 'API Branch Updated',
            'status' => 'Inactive',
        ])->assertOk()
            ->assertJsonPath('data.name', 'API Branch Updated')
            ->assertJsonPath('data.status', 'Inactive');

        $categoryResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Tenant' => $tenant->id,
            'Accept' => 'application/json',
        ])->postJson('/v1/api/category/store', [
            'name' => 'API Category',
            'foreign_name' => 'ប្រភេទ API',
            'status' => 'Active',
            'attachment' => $this->base64Png(),
        ]);

        $categoryResponse->assertOk()
            ->assertJsonPath('data.name', 'API Category');

        $categoryId = $categoryResponse->json('data.id');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Tenant' => $tenant->id,
            'Accept' => 'application/json',
        ])->patchJson('/v1/api/category/update/'.$categoryId, [
            'name' => 'API Category Updated',
            'status' => 'Inactive',
        ])->assertOk()
            ->assertJsonPath('data.name', 'API Category Updated')
            ->assertJsonPath('data.status', 'Inactive');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Tenant' => $tenant->id,
            'Accept' => 'application/json',
        ])->deleteJson('/v1/api/category/delete/'.$categoryId)
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Tenant' => $tenant->id,
            'Accept' => 'application/json',
        ])->deleteJson('/v1/api/branch/delete/'.$branchId)
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_item_api_supports_base64_uploads_and_partial_updates(): void
    {
        $admin = $this->createUser([
            'username' => 'tenant-item-api-admin',
            'email' => 'tenant-item-api-admin@example.com',
            'phone' => '56565656',
        ]);

        $tenant = $this->createSqliteTenant('tenant-item-api');
        $admin->tenants()->sync([$tenant->id]);

        tenancy()->initialize($tenant);
        $branch = Branch::query()->create([
            'code' => 'api-item-branch',
            'name' => 'API Item Branch',
            'foreign_name' => 'សាខាទំនិញ API',
            'status' => 'Active',
        ]);
        $category = Category::query()->create([
            'name' => 'API Item Category',
            'foreign_name' => 'ប្រភេទទំនិញ API',
            'status' => 'Active',
        ]);
        tenancy()->end();
        DB::purge('tenant');

        $token = $admin->createToken('tenant-item-api')->plainTextToken;

        $createResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Tenant' => $tenant->id,
            'Accept' => 'application/json',
        ])->postJson('/v1/api/item/store', [
            'category_id' => $category->id,
            'branch_id' => $branch->id,
            'sku' => 'API-ITEM-001',
            'name' => 'API Item',
            'foreign_name' => 'ទំនិញ API',
            'price' => 25,
            'stock' => 3,
            'status' => 'Active',
            'attachment' => $this->base64Png(),
            'attachments' => [$this->base64Png()],
        ]);

        $createResponse->assertOk()
            ->assertJsonPath('data.sku', 'API-ITEM-001')
            ->assertJsonPath('data.foreign_name', 'ទំនិញ API')
            ->assertJsonPath('data.branch_name', 'API Item Branch')
            ->assertJsonPath('data.branch_foreign_name', 'សាខាទំនិញ API')
            ->assertJsonPath('data.category_foreign_name', 'ប្រភេទទំនិញ API');

        $itemId = $createResponse->json('data.id');

        tenancy()->initialize($tenant);
        $item = Item::query()->with('galleries')->findOrFail($itemId);
        $this->assertNotNull($item->image_id);
        $this->assertCount(2, $item->galleries);
        tenancy()->end();
        DB::purge('tenant');

        $updateResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Tenant' => $tenant->id,
            'Accept' => 'application/json',
        ])->patchJson('/v1/api/item/update/'.$itemId, [
            'name' => 'API Item Updated',
            'is_featured' => true,
            'status' => 'Inactive',
        ]);

        $updateResponse->assertOk()
            ->assertJsonPath('data.name', 'API Item Updated')
            ->assertJsonPath('data.is_featured', true)
            ->assertJsonPath('data.status', 'Inactive');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Tenant' => $tenant->id,
            'Accept' => 'application/json',
        ])->getJson('/v1/api/item/edit/'.$itemId)
            ->assertOk()
            ->assertJsonPath('data.name', 'API Item Updated');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Tenant' => $tenant->id,
            'Accept' => 'application/json',
        ])->deleteJson('/v1/api/item/delete/'.$itemId)
            ->assertOk()
            ->assertJson(['success' => true]);

        tenancy()->initialize($tenant);
        $this->assertDatabaseMissing('items', [
            'id' => $itemId,
        ], 'tenant');
        tenancy()->end();
        DB::purge('tenant');
    }

    public function test_admin_can_download_item_excel_template_and_import_uom_options_and_variants(): void
    {
        $admin = $this->createUser([
            'username' => 'item-import-admin',
            'email' => 'item-import-admin@example.com',
            'phone' => '31313131',
        ]);
        $tenant = $this->createSqliteTenant('item-excel-import');
        $admin->tenants()->sync([$tenant->id]);

        tenancy()->initialize($tenant);
        Currency::query()->create([
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'decimal_places' => 2,
            'status' => 'Active',
        ]);
        $category = Category::query()->create([
            'name' => 'Drinks',
            'status' => 'Active',
        ]);
        $branch = Branch::query()->create([
            'code' => 'MAIN',
            'name' => 'Main Branch',
            'status' => 'Active',
        ]);
        $unit = UnitOfMeasure::query()->create([
            'code' => 'EA',
            'name' => 'Each',
            'symbol' => 'ea',
            'status' => 'Active',
        ]);
        $boxUnit = UnitOfMeasure::query()->create([
            'code' => 'BOX',
            'name' => 'Box',
            'symbol' => 'box',
            'status' => 'Active',
        ]);
        $group = UomGroup::query()->create([
            'code' => 'EACH',
            'name' => 'Each',
            'base_unit_id' => $unit->id,
            'status' => 'Active',
        ]);
        UomGroupUnit::query()->create([
            'uom_group_id' => $group->id,
            'unit_of_measure_id' => $unit->id,
            'alternate_quantity' => 1,
            'base_quantity' => 1,
            'conversion_factor_to_base' => 1,
            'is_base_unit' => true,
            'status' => 'Active',
        ]);
        UomGroupUnit::query()->create([
            'uom_group_id' => $group->id,
            'unit_of_measure_id' => $boxUnit->id,
            'alternate_quantity' => 1,
            'base_quantity' => 12,
            'conversion_factor_to_base' => 12,
            'is_base_unit' => false,
            'status' => 'Active',
        ]);
        tenancy()->end();
        DB::purge('tenant');

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->get(route('admin.items.index'))
            ->assertOk()
            ->assertSee('Excel Template')
            ->assertSee('Import Excel');

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->get(route('admin.items.import-template'))
            ->assertOk()
            ->assertDownload('items-import-template.xlsx');

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Items');
        $sheet->fromArray([
            [
                'sku', 'name', 'foreign_name', 'item_type', 'category', 'branch',
                'uom_group', 'price_list', 'currency', 'price', 'stock', 'status',
                'premium', 'featured', 'new_arrival', 'try_on', 'sort_order', 'description',
            ],
            [
                'IMP-001', 'Imported Coffee', 'កាហ្វេ', 'uom', 'Drinks', 'MAIN',
                'EACH', '', 'USD', 2.50, 12, 'Active',
                'No', 'Yes', 'No', 'No', 10, 'Created from Excel',
            ],
            [
                'IMP-VAR', 'Imported Shirt', '', 'variation', 'Drinks', 'MAIN',
                '', '', 'USD', 25, 0, 'Active',
                'No', 'No', 'Yes', 'No', 11, 'Variation created from Excel',
            ],
        ], null, 'A1', true);

        $uomPriceSheet = $spreadsheet->createSheet();
        $uomPriceSheet->setTitle('UOM Prices');
        $uomPriceSheet->fromArray([
            ['item_sku', 'unit_code', 'reduce_by_percent', 'price', 'auto', 'active'],
            ['IMP-001', 'EA', 0, '', 'Yes', 'Yes'],
            ['IMP-001', 'BOX', 5, 28.50, 'No', 'Yes'],
        ], null, 'A1', true);

        $optionSheet = $spreadsheet->createSheet();
        $optionSheet->setTitle('Options');
        $optionSheet->fromArray([
            [
                'item_sku', 'group_key', 'variation', 'group_name', 'group_foreign_name',
                'type', 'selection_type', 'required', 'min_selections', 'max_selections',
                'group_sort_order', 'group_status', 'value_key', 'value_name', 'value_foreign_name',
                'sku_suffix', 'color_hex', 'price_adjustment', 'default', 'value_sort_order', 'value_status',
            ],
            [
                'IMP-VAR', 'size', '', 'Size', '', 'variant', 'single', 'Yes', 1, 1,
                0, 'Active', 'size_s', 'Small', '', 'S', '', 0, 'No', 0, 'Active',
            ],
            [
                'IMP-VAR', 'size', '', '', '', '', '', '', '', '',
                '', '', 'size_m', 'Medium', '', 'M', '', 2.50, 'No', 1, 'Active',
            ],
        ], null, 'A1', true);

        $variantSheet = $spreadsheet->createSheet();
        $variantSheet->setTitle('Variants');
        $variantSheet->fromArray([
            ['item_sku', 'sku', 'barcode', 'name', 'price', 'stock', 'default', 'sort_order', 'status', 'option_value_keys'],
            ['IMP-VAR', 'IMP-VAR-S', '1000001', 'Small', 25, 5, 'Yes', 0, 'Active', 'size_s'],
            ['IMP-VAR', 'IMP-VAR-M', '1000002', 'Medium', 27.50, 7, 'No', 1, 'Active', 'size_m'],
        ], null, 'A1', true);

        $temporaryPath = tempnam(sys_get_temp_dir(), 'items-import-').'.xlsx';
        (new Xlsx($spreadsheet))->save($temporaryPath);
        $spreadsheet->disconnectWorksheets();

        try {
            $this->actingAs($admin)
                ->withSession(['admin_selected_tenant_id' => $tenant->id])
                ->post(route('admin.items.import'), [
                    'import_file' => new UploadedFile(
                        $temporaryPath,
                        'items-import.xlsx',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        null,
                        true
                    ),
                ])
                ->assertRedirect(route('admin.items.index'))
                ->assertSessionHas('status', '2 items imported successfully.');
        } finally {
            @unlink($temporaryPath);
        }

        tenancy()->initialize($tenant);
        $this->assertDatabaseHas('items', [
            'sku' => 'IMP-001',
            'name' => 'Imported Coffee',
            'item_type' => 'uom',
            'category_id' => $category->id,
            'branch_id' => $branch->id,
            'uom_group_id' => $group->id,
            'price' => 2.5,
            'stock' => 12,
            'is_featured' => true,
            'status' => 'Active',
        ], 'tenant');
        $uomItem = Item::query()->where('sku', 'IMP-001')->firstOrFail();
        $variationItem = Item::query()->where('sku', 'IMP-VAR')->firstOrFail();
        $this->assertDatabaseHas('item_uom_prices', [
            'item_id' => $uomItem->id,
            'unit_of_measure_id' => $boxUnit->id,
            'reduce_by_percent' => 5,
            'price' => 28.5,
            'is_auto' => false,
            'is_active' => true,
        ], 'tenant');
        $this->assertDatabaseHas('item_option_groups', [
            'item_id' => $variationItem->id,
            'name' => 'Size',
            'type' => 'variant',
            'selection_type' => 'single',
            'is_required' => true,
        ], 'tenant');
        $groupId = DB::connection('tenant')->table('item_option_groups')
            ->where('item_id', $variationItem->id)
            ->value('id');
        $this->assertDatabaseHas('item_option_values', [
            'item_option_group_id' => $groupId,
            'name' => 'Medium',
            'sku_suffix' => 'M',
            'price_adjustment' => 2.5,
        ], 'tenant');
        $this->assertDatabaseHas('item_variants', [
            'item_id' => $variationItem->id,
            'sku' => 'IMP-VAR-M',
            'barcode' => '1000002',
            'price' => 27.5,
            'stock' => 7,
        ], 'tenant');
        $mediumValueId = DB::connection('tenant')->table('item_option_values')
            ->where('item_option_group_id', $groupId)
            ->where('name', 'Medium')
            ->value('id');
        $mediumVariantId = DB::connection('tenant')->table('item_variants')
            ->where('sku', 'IMP-VAR-M')
            ->value('id');
        $this->assertDatabaseHas('item_variant_option_values', [
            'item_variant_id' => $mediumVariantId,
            'item_option_value_id' => $mediumValueId,
        ], 'tenant');
        tenancy()->end();
        DB::purge('tenant');
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

    private function createSqliteTenant(string $id): Tenant
    {
        $databaseName = 'tenant-'.$id.'.sqlite';
        $databasePath = database_path($databaseName);
        $alternateDatabasePath = database_path($id);

        DB::purge('tenant');

        if (File::exists($databasePath)) {
            File::delete($databasePath);
        }

        if (File::exists($alternateDatabasePath)) {
            File::delete($alternateDatabasePath);
        }

        File::put($databasePath, '');

        $this->tenantDatabasePaths[] = $databasePath;
        $this->tenantDatabasePaths[] = $alternateDatabasePath;

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

    private function base64Png(): string
    {
        return 'data:image/png;base64,'.base64_encode(
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jk9sAAAAASUVORK5CYII=', true)
        );
    }

    private function tenantRoleId(Tenant $tenant, string $roleName): int
    {
        tenancy()->initialize($tenant);
        $roleId = Role::query()->where('name', $roleName)->value('id');
        tenancy()->end();
        DB::purge('tenant');

        $this->assertNotNull($roleId, 'Expected tenant role ['.$roleName.'] to exist.');

        return (int) $roleId;
    }
}
