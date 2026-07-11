<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Gallery;
use App\Models\Item;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Role;
use App\Models\Slider;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class MobileApiSupportTest extends TestCase
{
    protected string $databasePath;
    protected array $tenantDatabasePaths = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = storage_path('framework/testing/mobile-api.sqlite');

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
        config()->set('tenancy.database.central_connection', 'central');

        DB::purge('central');
        DB::purge('mysql');

        Artisan::call('migrate:fresh', ['--database' => 'central']);
        Storage::fake('user');
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

    public function test_mobile_bootstrap_returns_tenant_catalog_sections(): void
    {
        $tenant = $this->createTenant('mobile-bootstrap');

        tenancy()->initialize($tenant);

        $branch = Branch::query()->create([
            'code' => 'bkk',
            'name' => 'BKK Branch',
            'location' => 'Phnom Penh',
            'sort_order' => 1,
            'status' => 'Active',
        ]);

        $category = Category::query()->create([
            'name' => 'Fresh Fruits',
            'foreign_name' => 'ផ្លែឈើស្រស់',
            'status' => 'Active',
        ]);

        Item::query()->create([
            'category_id' => $category->id,
            'branch_id' => $branch->id,
            'branch_name' => $branch->name,
            'sku' => 'APP-001',
            'name' => 'Organic Banana',
            'price' => 3.50,
            'stock' => 20,
            'is_featured' => true,
            'is_new_arrival' => true,
            'review_count' => 12,
            'status' => 'Active',
        ]);

        Slider::query()->create([
            'title' => 'Fresh Picks',
            'subtitle' => 'Everyday deal',
            'placement' => 'Mobile',
            'sort_order' => 1,
            'status' => 'Active',
        ]);

        tenancy()->end();
        DB::purge('tenant');

        $response = $this->withHeaders([
            'X-Tenant' => $tenant->id,
            'Accept' => 'application/json',
        ])->getJson('/v1/api/mobile/bootstrap');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.branches.0.name', 'BKK Branch')
            ->assertJsonPath('data.categories.0.name', 'Fresh Fruits')
            ->assertJsonPath('data.featured_products.0.name', 'Organic Banana')
            ->assertJsonPath('data.new_arrivals.0.sku', 'APP-001')
            ->assertJsonPath('data.banners.0.title', 'Fresh Picks');
    }

    public function test_mobile_api_supports_register_profile_address_favorite_order_and_notifications(): void
    {
        $tenant = $this->createTenant('mobile-flow');

        tenancy()->initialize($tenant);

        $branch = Branch::query()->create([
            'code' => 'cen',
            'name' => 'Central Market',
            'location' => 'Phnom Penh',
            'sort_order' => 1,
            'status' => 'Active',
        ]);

        $category = Category::query()->create([
            'name' => 'Bakery',
            'status' => 'Active',
        ]);

        $item = Item::query()->create([
            'category_id' => $category->id,
            'branch_id' => $branch->id,
            'branch_name' => $branch->name,
            'sku' => 'BREAD-001',
            'name' => 'Butter Bread',
            'price' => 5,
            'stock' => 10,
            'status' => 'Active',
            'is_featured' => true,
        ]);

        tenancy()->end();
        DB::purge('tenant');

        $registerResponse = $this->withHeaders([
            'X-Tenant' => $tenant->id,
            'Accept' => 'application/json',
        ])->postJson('/v1/api/mobile/auth/register', [
            'name' => 'Mobile Shopper',
            'username' => 'mobile-shopper',
            'email' => 'mobile@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'first_name' => 'Mobile',
            'last_name' => 'Shopper',
            'country_code' => '+855',
            'phone' => '12345678',
            'gender' => 'Female',
            'dob' => '1998-01-01',
        ]);

        $registerResponse->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.username', 'mobile-shopper');

        $token = $registerResponse->json('data.token');

        $profileResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant' => $tenant->id,
            'Accept' => 'application/json',
        ])->getJson('/v1/api/mobile/profile');

        $profileResponse->assertOk()
            ->assertJsonPath('data.email', 'mobile@example.com');

        $updateProfileResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant' => $tenant->id,
            'Accept' => 'application/json',
        ])->patchJson('/v1/api/mobile/profile', [
            'name' => 'Mobile Shopper Updated',
            'phone' => '87654321',
        ]);

        $updateProfileResponse->assertOk()
            ->assertJsonPath('data.name', 'Mobile Shopper Updated')
            ->assertJsonPath('data.phone', '87654321');

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant' => $tenant->id,
            'Accept' => 'application/json',
        ])->patchJson('/v1/api/mobile/profile', [
            'password' => 'secret456',
        ])->assertOk();

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant' => $tenant->id,
            'Accept' => 'application/json',
        ])->patchJson('/v1/api/mobile/profile', [
            'profile' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a8XcAAAAASUVORK5CYII=',
        ])->assertOk()
            ->assertJsonPath('success', true);

        $this->withHeaders([
            'X-Tenant' => $tenant->id,
            'Accept' => 'application/json',
        ])->postJson('/v1/api/mobile/auth/login', [
            'username' => 'mobile-shopper',
            'password' => 'secret456',
        ])->assertOk()
            ->assertJsonPath('data.user.username', 'mobile-shopper');

        $centralUser = User::on('central')->where('username', 'mobile-shopper')->first();
        $this->assertNotNull($centralUser);
        $this->assertNotNull($centralUser->profile_id);

        $addressResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant' => $tenant->id,
            'Accept' => 'application/json',
        ])->postJson('/v1/api/mobile/addresses', [
            'label' => 'Home',
            'recipient_name' => 'Mobile Shopper',
            'country_code' => '+855',
            'phone' => '87654321',
            'address_line' => 'Street 271, BKK',
            'city' => 'Phnom Penh',
            'note' => 'Near market',
            'is_default' => true,
        ]);

        $addressResponse->assertCreated()
            ->assertJsonPath('data.label', 'Home')
            ->assertJsonPath('data.is_default', true);

        $addressId = $addressResponse->json('data.id');

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant' => $tenant->id,
            'Accept' => 'application/json',
        ])->postJson('/v1/api/mobile/favorites/toggle', [
            'item_id' => $item->id,
        ])->assertOk()
            ->assertJsonPath('data.is_favorite', true);

        $favoritesResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant' => $tenant->id,
            'Accept' => 'application/json',
        ])->getJson('/v1/api/mobile/favorites');

        $favoritesResponse->assertOk()
            ->assertJsonPath('data.ids.0', $item->id)
            ->assertJsonPath('data.products.0.name', 'Butter Bread');

        $orderResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant' => $tenant->id,
            'Accept' => 'application/json',
        ])->postJson('/v1/api/mobile/orders', [
            'address_id' => $addressId,
            'items' => [
                [
                    'item_id' => $item->id,
                    'quantity' => 2,
                ],
            ],
            'note' => 'Please deliver soon',
        ]);

        $orderResponse->assertCreated()
            ->assertJsonPath('data.total_items', 2)
            ->assertJsonPath('data.items.0.name', 'Butter Bread');

        $orderId = $orderResponse->json('data.id');

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant' => $tenant->id,
            'Accept' => 'application/json',
        ])->getJson('/v1/api/mobile/orders')
            ->assertOk()
            ->assertJsonPath('data.0.id', $orderId);

        $notificationsResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant' => $tenant->id,
            'Accept' => 'application/json',
        ])->getJson('/v1/api/mobile/notifications');

        $notificationsResponse->assertOk()
            ->assertJsonPath('meta.unread_count', 2);

        $notificationId = $notificationsResponse->json('data.0.id');

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant' => $tenant->id,
            'Accept' => 'application/json',
        ])->postJson('/v1/api/mobile/notifications/' . $notificationId . '/read')
            ->assertOk()
            ->assertJsonPath('data.id', $notificationId);

        tenancy()->initialize($tenant);
        $this->assertSame(1, Order::query()->count());
        $this->assertSame(2, Notification::query()->count());
        $tenantUser = User::query()->find(1);
        $this->assertNotNull($tenantUser);
        $this->assertSame('mobile-shopper', $tenantUser->username);
        tenancy()->end();
        DB::purge('tenant');
    }

    public function test_mobile_login_accepts_existing_tenant_user_accounts(): void
    {
        $tenant = $this->createTenant('mobile-tenant-login');

        tenancy()->initialize($tenant);

        User::query()->create([
            'name' => 'Vanna',
            'username' => 'Vanna',
            'email' => 'vanna@example.com',
            'password' => Hash::make('123456'),
            'first_name' => 'Van',
            'last_name' => 'Na',
            'phone' => '012345678',
            'status' => 'Active',
        ]);

        tenancy()->end();
        DB::purge('tenant');

        $loginResponse = $this->withHeaders([
            'X-Tenant' => $tenant->id,
            'Accept' => 'application/json',
        ])->postJson('/v1/api/mobile/auth/login', [
            'username' => 'Vanna',
            'password' => '123456',
        ]);

        $loginResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.username', 'Vanna');

        $token = $loginResponse->json('data.token');

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant' => $tenant->id,
            'Accept' => 'application/json',
        ])->getJson('/v1/api/mobile/auth/me')
            ->assertOk()
            ->assertJsonPath('data.username', 'Vanna');

        $centralUser = User::on('central')->where('username', 'Vanna')->first();

        $this->assertNotNull($centralUser);
        $this->assertTrue(
            $centralUser->tenants()->where('tenants.id', $tenant->id)->exists()
        );
    }

    public function test_mobile_profile_upload_is_mirrored_to_tenant_gallery_for_file_manager_and_edit_preview(): void
    {
        $tenant = $this->createTenant('mobile-profile-mirror');

        $admin = User::on('central')->create([
            'name' => 'Admin Root',
            'username' => 'admin-root',
            'email' => 'admin-root@example.com',
            'password' => Hash::make('secret123'),
            'phone' => '18889999',
            'status' => 'Active',
        ]);

        $admin->tenants()->sync([$tenant->id]);

        $registerResponse = $this->withHeaders([
            'X-Tenant' => $tenant->id,
            'Accept' => 'application/json',
        ])->postJson('/v1/api/mobile/auth/register', [
            'name' => 'Mirror Shopper',
            'username' => 'mirror-shopper',
            'email' => 'mirror@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'country_code' => '+855',
            'phone' => '17776666',
        ]);

        $registerResponse->assertCreated();
        $token = $registerResponse->json('data.token');

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant' => $tenant->id,
            'Accept' => 'application/json',
        ])->patchJson('/v1/api/mobile/profile', [
            'profile' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a8XcAAAAASUVORK5CYII=',
        ])->assertOk();

        $centralUser = User::on('central')->where('username', 'mirror-shopper')->firstOrFail();
        $centralGallery = Gallery::on('central')->findOrFail($centralUser->profile_id);

        tenancy()->initialize($tenant);
        $tenantUser = User::query()->where('username', 'mirror-shopper')->firstOrFail();
        $tenantGallery = Gallery::query()
            ->where('gallarieable_type', User::class)
            ->where('gallarieable_id', $tenantUser->id)
            ->first();
        $this->assertNotNull($tenantGallery);
        $this->assertSame($centralGallery->name, $tenantGallery->name);
        $this->assertSame($tenantGallery->id, $tenantUser->profile_id);
        tenancy()->end();
        DB::purge('tenant');

        Storage::disk('user')->assertExists($centralGallery->name);

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->get(route('admin.file-manager.index', ['directory' => 'users']))
            ->assertOk()
            ->assertSee($centralGallery->name);

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->get(route('admin.tenant-users.edit', ['tenant_user' => $tenantUser->id]))
            ->assertOk()
            ->assertSee(Storage::disk('user')->url($centralGallery->name), false);
    }

    public function test_mobile_profile_repairs_stale_central_image_after_tenant_web_update(): void
    {
        $tenant = $this->createTenant('mobile-profile-repair');

        $admin = User::on('central')->create([
            'name' => 'Admin Root',
            'username' => 'admin-root-repair',
            'email' => 'admin-root-repair@example.com',
            'password' => Hash::make('secret123'),
            'phone' => '19998888',
            'status' => 'Active',
        ]);

        $admin->tenants()->sync([$tenant->id]);

        $registerResponse = $this->withHeaders([
            'X-Tenant' => $tenant->id,
            'Accept' => 'application/json',
        ])->postJson('/v1/api/mobile/auth/register', [
            'name' => 'Repair Shopper',
            'username' => 'repair-shopper',
            'email' => 'repair@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'country_code' => '+855',
            'phone' => '16667777',
            'gender' => 'Female',
        ]);

        $registerResponse->assertCreated();
        $token = $registerResponse->json('data.token');

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant' => $tenant->id,
            'Accept' => 'application/json',
        ])->patchJson('/v1/api/mobile/profile', [
            'profile' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a8XcAAAAASUVORK5CYII=',
        ])->assertOk();

        $centralUser = User::on('central')->where('username', 'repair-shopper')->firstOrFail();
        $oldCentralGallery = Gallery::on('central')->findOrFail($centralUser->profile_id);
        $oldCentralFileName = $oldCentralGallery->name;

        tenancy()->initialize($tenant);
        $tenantUser = User::query()->where('username', 'repair-shopper')->firstOrFail();
        $tenantAdminRoleId = Role::query()->where('name', 'tenant-admin')->value('id');
        tenancy()->end();
        DB::purge('tenant');

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->post(route('admin.tenant-users.update', ['tenant_user' => $tenantUser->id]), [
                'name' => 'Repair Shopper',
                'username' => 'repair-shopper',
                'email' => 'repair@example.com',
                'phone' => '16667777',
                'password' => '',
                'password_confirmation' => '',
                'first_name' => 'Repair',
                'last_name' => 'Shopper',
                'country_code' => '855',
                'gender' => 'Female',
                'status' => 'Active',
                'profile' => UploadedFile::fake()->image('repair-shopper-updated.png'),
                'roles' => [$tenantAdminRoleId],
                '_method' => 'PUT',
            ])
            ->assertRedirect(route('admin.tenant-users.index'));

        Storage::disk('user')->assertMissing($oldCentralFileName);

        $loginResponse = $this->withHeaders([
            'X-Tenant' => $tenant->id,
            'Accept' => 'application/json',
        ])->postJson('/v1/api/mobile/auth/login', [
            'username' => 'repair-shopper',
            'password' => 'secret123',
        ]);

        $loginResponse->assertOk()
            ->assertJsonPath('success', true);

        $freshToken = $loginResponse->json('data.token');

        tenancy()->initialize($tenant);
        $tenantUser = User::query()->findOrFail($centralUser->id);
        $tenantGallery = Gallery::query()->findOrFail($tenantUser->profile_id);
        tenancy()->end();
        DB::purge('tenant');

        Storage::disk('user')->assertExists($tenantGallery->name);

        $profileResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $freshToken,
            'X-Tenant' => $tenant->id,
            'Accept' => 'application/json',
        ])->getJson('/v1/api/mobile/profile');

        $profileResponse->assertOk()
            ->assertJsonPath('data.profile_image_url', Storage::disk('user')->url($tenantGallery->name));

        $centralUser = User::on('central')->findOrFail($centralUser->id);
        $centralGallery = Gallery::on('central')->findOrFail($centralUser->profile_id);

        $this->assertSame($tenantGallery->name, $centralGallery->name);
    }

    public function test_mobile_profile_image_url_stays_stable_after_mobile_upload_and_reload(): void
    {
        $tenant = $this->createTenant('mobile-profile-stable');

        $registerResponse = $this->withHeaders([
            'X-Tenant' => $tenant->id,
            'Accept' => 'application/json',
        ])->postJson('/v1/api/mobile/auth/register', [
            'name' => 'Stable Shopper',
            'username' => 'stable-shopper',
            'email' => 'stable@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'country_code' => '+855',
            'phone' => '15554444',
        ]);

        $registerResponse->assertCreated();
        $token = $registerResponse->json('data.token');

        $updateResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant' => $tenant->id,
            'Accept' => 'application/json',
        ])->patchJson('/v1/api/mobile/profile', [
            'profile' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a8XcAAAAASUVORK5CYII=',
        ]);

        $updateResponse->assertOk()
            ->assertJsonPath('success', true);

        $firstImageUrl = $updateResponse->json('data.profile_image_url');
        $this->assertNotEmpty($firstImageUrl);

        $profileResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant' => $tenant->id,
            'Accept' => 'application/json',
        ])->getJson('/v1/api/mobile/profile');

        $profileResponse->assertOk()
            ->assertJsonPath('data.profile_image_url', $firstImageUrl);

        $meResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant' => $tenant->id,
            'Accept' => 'application/json',
        ])->getJson('/v1/api/mobile/auth/me');

        $meResponse->assertOk()
            ->assertJsonPath('data.profile_image_url', $firstImageUrl);

        $centralUser = User::on('central')->where('username', 'stable-shopper')->firstOrFail();
        $centralGallery = Gallery::on('central')->findOrFail($centralUser->profile_id);
        Storage::disk('user')->assertExists($centralGallery->name);

        tenancy()->initialize($tenant);
        $tenantUser = User::query()->findOrFail($centralUser->id);
        $tenantGallery = Gallery::query()->findOrFail($tenantUser->profile_id);
        tenancy()->end();
        DB::purge('tenant');

        $this->assertSame($centralGallery->name, $tenantGallery->name);
    }

    private function createTenant(string $id): Tenant
    {
        $databaseName = 'tenant-' . $id . '.sqlite';
        $databasePath = database_path($databaseName);
        $fallbackDatabasePath = database_path($id);

        DB::purge('tenant');

        if (File::exists($databasePath)) {
            File::delete($databasePath);
        }

        if (File::exists($fallbackDatabasePath)) {
            File::delete($fallbackDatabasePath);
        }

        File::put($databasePath, '');

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
}
