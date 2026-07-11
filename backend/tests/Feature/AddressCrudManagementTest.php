<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AddressCrudManagementTest extends TestCase
{
    protected string $databasePath;
    protected array $tenantDatabasePaths = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = storage_path('framework/testing/address-crud.sqlite');

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

    public function test_address_listing_page_is_available_for_selected_tenant(): void
    {
        $admin = $this->createAdminUser();
        $tenant = $this->createTenant('tenant-address-list');
        $admin->tenants()->sync([$tenant->id]);

        tenancy()->initialize($tenant);
        $tenantUser = User::query()->create([
            'name' => 'Tenant User',
            'username' => 'tenant-user',
            'phone' => '12345678',
            'password' => Hash::make('secret123'),
            'status' => 'Active',
        ]);
        Address::query()->create([
            'user_id' => $tenantUser->id,
            'label' => 'Home',
            'recipient_name' => 'Hhh Shopper',
            'code' => '+855',
            'phone' => '12345678',
            'address_line' => 'Street 271, Boeng Keng Kang',
            'city' => 'Phnom Penh',
            'note' => 'Near the coffee shop',
            'latitude' => 11.5564,
            'longitude' => 104.9282,
            'is_default' => true,
        ]);
        tenancy()->end();
        DB::purge('tenant');

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->get(route('admin.addresses.index'))
            ->assertOk()
            ->assertSee('Address Directory')
            ->assertSee('Hhh Shopper')
            ->assertSee('Create Address')
            ->assertSee('11.556400');

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->get(route('admin.addresses.create'))
            ->assertOk()
            ->assertSee('Select User')
            ->assertSee('Address Details')
            ->assertSee('Latitude')
            ->assertSee('Longitude');
    }

    public function test_address_can_be_created_and_updated_for_selected_user(): void
    {
        $admin = $this->createAdminUser([
            'username' => 'address-admin',
            'email' => 'address-admin@example.com',
        ]);
        $tenant = $this->createTenant('tenant-address-write');
        $admin->tenants()->sync([$tenant->id]);

        tenancy()->initialize($tenant);
        $tenantUser = User::query()->create([
            'name' => 'Target User',
            'username' => 'target-user',
            'phone' => '22223333',
            'password' => Hash::make('secret123'),
            'status' => 'Active',
        ]);
        tenancy()->end();
        DB::purge('tenant');

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->post(route('admin.addresses.store'), [
                'user_id' => $tenantUser->id,
                'label' => 'Home',
                'recipient_name' => 'Hhh Shopper',
                'code' => '+855',
                'phone' => '12345678',
                'address_line' => 'Street 271, Boeng Keng Kang',
                'city' => 'Phnom Penh',
                'note' => 'Near the coffee shop',
                'latitude' => '11.5564000',
                'longitude' => '104.9282000',
                'is_default' => '1',
            ])
            ->assertRedirect(route('admin.addresses.index'))
            ->assertSessionHas('status', 'Address created successfully.');

        tenancy()->initialize($tenant);
        $address = Address::query()->first();
        $this->assertNotNull($address);
        $this->assertSame($tenantUser->id, $address->user_id);
        $this->assertTrue($address->is_default);
        $this->assertSame(11.5564, $address->latitude);
        $this->assertSame(104.9282, $address->longitude);
        tenancy()->end();
        DB::purge('tenant');

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->put(route('admin.addresses.update', ['address' => $address->id]), [
                'user_id' => $tenantUser->id,
                'label' => 'Office',
                'recipient_name' => 'Hhh Shopper',
                'code' => '+855',
                'phone' => '99998888',
                'address_line' => 'Street 302, BKK1',
                'city' => 'Phnom Penh',
                'note' => 'Updated note',
                'latitude' => '11.5678000',
                'longitude' => '104.9211000',
                'is_default' => '0',
            ])
            ->assertRedirect(route('admin.addresses.index'))
            ->assertSessionHas('status', 'Address updated successfully.');

        tenancy()->initialize($tenant);
        $address->refresh();
        $this->assertSame('Office', $address->label);
        $this->assertSame('99998888', $address->phone);
        $this->assertSame('Street 302, BKK1', $address->address_line);
        $this->assertSame(11.5678, $address->latitude);
        $this->assertSame(104.9211, $address->longitude);
        $this->assertFalse($address->is_default);
        tenancy()->end();
        DB::purge('tenant');
    }

    private function createAdminUser(array $attributes = []): User
    {
        return User::query()->create(array_merge([
            'name' => 'Admin User',
            'username' => 'admin-user',
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
}
