<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Category;
use App\Models\Gallery;
use App\Models\Item;
use App\Models\Promotion;
use App\Models\Slider;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminFileManagerTest extends TestCase
{
    protected string $databasePath;
    protected string $fileManagerRoot;
    protected array $tenantDatabasePaths = [];
    protected array $fileManagerTenantPrefixes = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = storage_path('framework/testing/admin-file-manager.sqlite');

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
        $this->fileManagerRoot = storage_path('framework/testing/file-manager');
        config()->set('filesystems.disks.file_manager', [
            'driver' => 'local',
            'root' => $this->fileManagerRoot,
            'url' => env('APP_URL', 'http://localhost') . '/storage/testing/file-manager',
            'visibility' => 'public',
            'throw' => false,
        ]);
        config()->set('filesystems.disks.item', [
            'driver' => 'local',
            'root' => storage_path('framework/testing/file-manager-items'),
            'url' => env('APP_URL', 'http://localhost') . '/storage/testing/file-manager-items',
            'visibility' => 'public',
            'throw' => false,
        ]);
        config()->set('filesystems.disks.category', [
            'driver' => 'local',
            'root' => storage_path('framework/testing/file-manager-categories'),
            'url' => env('APP_URL', 'http://localhost') . '/storage/testing/file-manager-categories',
            'visibility' => 'public',
            'throw' => false,
        ]);
        config()->set('filesystems.disks.slider', [
            'driver' => 'local',
            'root' => storage_path('framework/testing/file-manager-sliders'),
            'url' => env('APP_URL', 'http://localhost') . '/storage/testing/file-manager-sliders',
            'visibility' => 'public',
            'throw' => false,
        ]);
        config()->set('filesystems.disks.promotion', [
            'driver' => 'local',
            'root' => storage_path('framework/testing/file-manager-promotions'),
            'url' => env('APP_URL', 'http://localhost') . '/storage/testing/file-manager-promotions',
            'visibility' => 'public',
            'throw' => false,
        ]);
        config()->set('filesystems.disks.user', [
            'driver' => 'local',
            'root' => storage_path('framework/testing/file-manager-users'),
            'url' => env('APP_URL', 'http://localhost') . '/storage/testing/file-manager-users',
            'visibility' => 'public',
            'throw' => false,
        ]);

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

        foreach ($this->fileManagerTenantPrefixes as $tenantPrefix) {
            if (isset($this->fileManagerRoot) && File::exists($this->fileManagerRoot . DIRECTORY_SEPARATOR . $tenantPrefix)) {
                File::deleteDirectory($this->fileManagerRoot . DIRECTORY_SEPARATOR . $tenantPrefix);
            }
        }

        if (isset($this->fileManagerRoot) && File::exists($this->fileManagerRoot)) {
            File::deleteDirectory($this->fileManagerRoot);
        }

        foreach (['item', 'category', 'slider', 'promotion', 'user'] as $disk) {
            $root = (string) config("filesystems.disks.{$disk}.root");

            if ($root !== '' && File::exists($root)) {
                File::deleteDirectory($root);
            }
        }

        parent::tearDown();
    }

    public function test_admin_can_upload_and_delete_files_in_file_manager(): void
    {
        $admin = $this->createUser([
            'username' => 'file-admin',
            'email' => 'file-admin@example.com',
            'phone' => '71111111',
        ]);
        $tenant = $this->createTenant('file-manager-a');
        $admin->tenants()->sync([$tenant->id]);

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->post(route('admin.file-manager.folders.store'), [
                'current_directory' => '',
                'folder_name' => 'banners',
            ])
            ->assertRedirect(route('admin.file-manager.index'));

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->post(route('admin.file-manager.store'), [
                'current_directory' => 'banners',
                'files' => [UploadedFile::fake()->createWithContent('Hero Banner (Final).png', 'first-version')],
            ])
            ->assertRedirect(route('admin.file-manager.index', ['directory' => 'banners']));

        $storedFilePath = $tenant->id . '/banners/Hero Banner (Final).png';

        $this->assertTrue(Storage::disk('file_manager')->exists($storedFilePath));
        $this->assertSame('first-version', Storage::disk('file_manager')->get($storedFilePath));

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->post(route('admin.file-manager.store'), [
                'current_directory' => 'banners',
                'files' => [UploadedFile::fake()->createWithContent('Hero Banner (Final).png', 'replacement-version')],
            ])
            ->assertRedirect(route('admin.file-manager.index', ['directory' => 'banners']));

        $this->assertCount(1, Storage::disk('file_manager')->files($tenant->id . '/banners'));
        $this->assertSame('replacement-version', Storage::disk('file_manager')->get($storedFilePath));

        $storedRelativePath = ltrim(str_replace($tenant->id, '', $storedFilePath), '/');

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->get(route('admin.file-manager.index', ['directory' => 'banners']))
            ->assertOk()
            ->assertSee('File Manager')
            ->assertSee('Hero Banner (Final).png', false)
            ->assertSee('Copy Link')
            ->assertSee('banners');

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->patch(route('admin.file-manager.rename'), [
                'current_directory' => 'banners',
                'path' => $storedRelativePath,
                'new_name' => 'Marketing Hero.png',
            ])
            ->assertRedirect(route('admin.file-manager.index', ['directory' => 'banners']))
            ->assertSessionHas('status', 'File renamed successfully.');

        $renamedRelativePath = 'banners/Marketing Hero.png';
        $renamedStoredPath = $tenant->id . '/' . $renamedRelativePath;
        $this->assertFalse(Storage::disk('file_manager')->exists($storedFilePath));
        $this->assertTrue(Storage::disk('file_manager')->exists($renamedStoredPath));
        $this->assertSame('replacement-version', Storage::disk('file_manager')->get($renamedStoredPath));

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->delete(route('admin.file-manager.destroy'), [
                'current_directory' => 'banners',
                'path' => $renamedRelativePath,
                'entry_type' => 'file',
            ])
            ->assertRedirect(route('admin.file-manager.index', ['directory' => 'banners']));

        $this->assertEmpty(Storage::disk('file_manager')->files($tenant->id . '/banners'));
    }

    public function test_file_manager_files_are_scoped_to_selected_tenant(): void
    {
        $admin = $this->createUser([
            'username' => 'file-scope-admin',
            'email' => 'file-scope-admin@example.com',
            'phone' => '72222222',
        ]);
        $tenantA = $this->createTenant('file-scope-a');
        $tenantB = $this->createTenant('file-scope-b');
        $admin->tenants()->sync([$tenantA->id, $tenantB->id]);

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenantA->id])
            ->post(route('admin.file-manager.store'), [
                'current_directory' => '',
                'files' => [UploadedFile::fake()->create('tenant-a-catalog.pdf', 120, 'application/pdf')],
            ])
            ->assertRedirect(route('admin.file-manager.index'));

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenantB->id])
            ->get(route('admin.file-manager.index'))
            ->assertOk()
            ->assertDontSee('tenant-a-catalog', false);

        $this->assertCount(1, Storage::disk('file_manager')->files($tenantA->id));
        $this->assertCount(0, Storage::disk('file_manager')->files($tenantB->id));
    }

    public function test_file_manager_all_files_table_is_not_limited_to_six_files(): void
    {
        $admin = $this->createUser([
            'username' => 'all-files-admin',
            'email' => 'all-files-admin@example.com',
            'phone' => '74444444',
        ]);
        $tenant = $this->createTenant('file-manager-all-files');
        $admin->tenants()->sync([$tenant->id]);

        $files = collect(range(1, 8))
            ->map(fn (int $index) => UploadedFile::fake()->createWithContent("document-{$index}.txt", "content-{$index}"))
            ->all();

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->post(route('admin.file-manager.store'), [
                'current_directory' => '',
                'files' => $files,
            ])
            ->assertRedirect(route('admin.file-manager.index'));

        $response = $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->get(route('admin.file-manager.index'))
            ->assertOk()
            ->assertSee('All Files')
            ->assertSee('File Types')
            ->assertSee('datatable-file-manager')
            ->assertSee('Rename')
            ->assertSee('Delete Selected')
            ->assertSee('selectAllFiles')
            ->assertDontSee('Recent Files');

        foreach (range(1, 8) as $index) {
            $response->assertSee("document-{$index}.txt", false);
        }

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->from(route('admin.file-manager.index'))
            ->patch(route('admin.file-manager.rename'), [
                'current_directory' => '',
                'path' => 'document-1.txt',
                'new_name' => 'document-2.txt',
            ])
            ->assertRedirect(route('admin.file-manager.index'))
            ->assertSessionHasErrors('new_name');

        $this->assertTrue(Storage::disk('file_manager')->exists($tenant->id . '/document-1.txt'));
        $this->assertTrue(Storage::disk('file_manager')->exists($tenant->id . '/document-2.txt'));

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->delete(route('admin.file-manager.bulk-destroy'), [
                'current_directory' => '',
                'paths' => ['document-1.txt', 'document-3.txt', 'document-8.txt'],
            ])
            ->assertRedirect(route('admin.file-manager.index'))
            ->assertSessionHas('status', '3 files deleted successfully.');

        $this->assertFalse(Storage::disk('file_manager')->exists($tenant->id . '/document-1.txt'));
        $this->assertFalse(Storage::disk('file_manager')->exists($tenant->id . '/document-3.txt'));
        $this->assertFalse(Storage::disk('file_manager')->exists($tenant->id . '/document-8.txt'));
        $this->assertTrue(Storage::disk('file_manager')->exists($tenant->id . '/document-2.txt'));
    }

    public function test_file_manager_lists_all_tenant_image_libraries_from_galleries(): void
    {
        $admin = $this->createUser([
            'username' => 'gallery-admin',
            'email' => 'gallery-admin@example.com',
            'phone' => '73333333',
        ]);
        $tenant = $this->createTenant('file-gallery-a');
        $admin->tenants()->sync([$tenant->id]);

        tenancy()->initialize($tenant);

        $category = Category::query()->create([
            'name' => 'Snacks',
            'status' => 'Active',
        ]);
        $categoryGallery = $category->galleries()->create([
            'type' => 'thumbnail',
            'status' => 'Active',
            'name' => 'category-existing.png',
        ]);
        $category->forceFill(['image_id' => $categoryGallery->id])->save();
        Storage::disk('category')->put('category-existing.png', 'category-image');

        $item = Item::query()->create([
            'category_id' => $category->id,
            'sku' => 'SKU-001',
            'name' => 'Potato Chips',
            'price' => 1.5,
            'stock' => 10,
            'status' => 'Active',
        ]);
        $itemGallery = $item->galleries()->create([
            'type' => 'thumbnail',
            'status' => 'Active',
            'name' => 'item-existing.png',
        ]);
        $item->forceFill(['image_id' => $itemGallery->id])->save();
        Storage::disk('item')->put('item-existing.png', 'item-image');

        $slider = Slider::query()->create([
            'title' => 'Homepage Hero',
            'placement' => 'Website',
            'status' => 'Active',
        ]);
        $sliderGallery = $slider->galleries()->create([
            'type' => 'thumbnail',
            'status' => 'Active',
            'name' => 'slider-existing.png',
        ]);
        $slider->forceFill(['image_id' => $sliderGallery->id])->save();
        Storage::disk('slider')->put('slider-existing.png', 'slider-image');

        $promotion = Promotion::query()->create([
            'code' => 'PROMO-001',
            'name' => 'Weekend Deal',
            'type' => Promotion::TYPE_ITEM_PRICE,
            'start_at' => Carbon::parse('2026-03-01 00:00:00'),
            'end_at' => Carbon::parse('2026-03-31 23:59:59'),
            'status' => 'Active',
        ]);
        $promotionGallery = $promotion->galleries()->create([
            'type' => 'thumbnail',
            'status' => 'Active',
            'name' => 'promotion-existing.png',
        ]);
        $promotion->forceFill(['image_id' => $promotionGallery->id])->save();
        Storage::disk('promotion')->put('promotion-existing.png', 'promotion-image');

        $tenantUser = User::query()->create([
            'name' => 'Tenant Staff',
            'username' => 'tenant-staff',
            'email' => 'tenant-staff@example.com',
            'password' => Hash::make('secret123'),
            'phone' => '74444444',
            'status' => 'Active',
        ]);
        $userGallery = $tenantUser->galleries()->create([
            'type' => 'thumbnail',
            'status' => 'Active',
            'name' => 'user-existing.png',
        ]);
        $tenantUser->forceFill(['profile_id' => $userGallery->id])->save();
        Storage::disk('user')->put('user-existing.png', 'user-image');

        tenancy()->end();
        DB::purge('tenant');

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->get(route('admin.file-manager.index'))
            ->assertOk()
            ->assertSee('Item Images')
            ->assertSee('Category Images')
            ->assertSee('Slider Images')
            ->assertSee('Promotion Images')
            ->assertSee('User Images');

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->get(route('admin.file-manager.index', ['directory' => 'items']))
            ->assertOk()
            ->assertSee('item-existing.png');

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->get(route('admin.file-manager.index', ['directory' => 'categories']))
            ->assertOk()
            ->assertSee('category-existing.png');

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->get(route('admin.file-manager.index', ['directory' => 'sliders']))
            ->assertOk()
            ->assertSee('slider-existing.png');

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->get(route('admin.file-manager.index', ['directory' => 'promotions']))
            ->assertOk()
            ->assertSee('promotion-existing.png');

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->get(route('admin.file-manager.index', ['directory' => 'users']))
            ->assertOk()
            ->assertSee('user-existing.png');
    }

    public function test_admin_can_restore_and_replace_linked_item_images_from_my_files(): void
    {
        $admin = $this->createUser([
            'username' => 'library-admin',
            'email' => 'library-admin@example.com',
            'phone' => '75555555',
        ]);
        $tenant = $this->createTenant('file-library-a');
        $admin->tenants()->sync([$tenant->id]);

        tenancy()->initialize($tenant);
        $category = Category::query()->create([
            'name' => 'Imported Items',
            'status' => 'Active',
        ]);
        $items = collect([
            ['sku' => 'IMPORT-001', 'name' => 'Imported Item One'],
            ['sku' => 'IMPORT-002', 'name' => 'Imported Item Two'],
        ])->map(function (array $attributes) use ($category) {
            $item = Item::query()->create([
                'category_id' => $category->id,
                'sku' => $attributes['sku'],
                'name' => $attributes['name'],
                'price' => 10,
                'stock' => 5,
                'status' => 'Active',
            ]);
            $gallery = $item->galleries()->create([
                'type' => 'thumbnail',
                'status' => 'Active',
                'name' => 'imported-shared.png',
            ]);
            $item->forceFill(['image_id' => $gallery->id])->save();

            return $item;
        });
        tenancy()->end();
        DB::purge('tenant');

        $restoredUpload = UploadedFile::fake()->image('imported-shared.png');
        $restoredContents = file_get_contents($restoredUpload->getRealPath());

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->post(route('admin.file-manager.store'), [
                'current_directory' => '',
                'files' => [$restoredUpload],
            ])
            ->assertRedirect(route('admin.file-manager.index'))
            ->assertSessionHas('status', 'Files uploaded and 2 linked image record(s) restored.');

        $this->assertTrue(Storage::disk('item')->exists('imported-shared.png'));
        $this->assertSame($restoredContents, Storage::disk('item')->get('imported-shared.png'));
        $this->assertFalse(Storage::disk('file_manager')->exists($tenant->id . '/imported-shared.png'));

        tenancy()->initialize($tenant);
        foreach ($items as $item) {
            $this->assertNotNull($item->fresh()->image_url);
        }
        tenancy()->end();
        DB::purge('tenant');

        $replacementUpload = UploadedFile::fake()->image('replacement.png', 20, 20);
        $replacementContents = file_get_contents($replacementUpload->getRealPath());

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->post(route('admin.file-manager.store'), [
                'current_directory' => '',
                'files' => [$replacementUpload],
            ])
            ->assertRedirect(route('admin.file-manager.index'));

        $this->assertTrue(Storage::disk('file_manager')->exists($tenant->id . '/replacement.png'));

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->patch(route('admin.file-manager.replace-from-my-files'), [
                'current_directory' => 'items',
                'target_path' => 'items/imported-shared.png',
                'source_path' => 'replacement.png',
            ])
            ->assertRedirect(route('admin.file-manager.index', ['directory' => 'items']))
            ->assertSessionHas('status', 'Linked image replaced successfully. The My Files staging copy was removed.');

        $this->assertSame($replacementContents, Storage::disk('item')->get('imported-shared.png'));
        $this->assertFalse(Storage::disk('file_manager')->exists($tenant->id . '/replacement.png'));

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->get(route('admin.file-manager.index', ['directory' => 'items']))
            ->assertOk()
            ->assertSee('Linked: IMPORT-001 — Imported Item One')
            ->assertSee('Replace');

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->post(route('admin.file-manager.store'), [
                'current_directory' => 'items',
                'files' => [UploadedFile::fake()->image('unassigned.png')],
            ])
            ->assertForbidden();

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->delete(route('admin.file-manager.bulk-destroy'), [
                'current_directory' => 'items',
                'paths' => ['items/imported-shared.png'],
            ])
            ->assertRedirect(route('admin.file-manager.index', ['directory' => 'items']))
            ->assertSessionHas('status', '1 file deleted successfully.');

        tenancy()->initialize($tenant);
        foreach ($items as $item) {
            $this->assertNull($item->fresh()->image_id);
        }
        tenancy()->end();
        DB::purge('tenant');

        $this->assertFalse(Storage::disk('item')->exists('imported-shared.png'));
    }

    public function test_deleting_attached_gallery_file_clears_owner_reference(): void
    {
        $admin = $this->createUser([
            'username' => 'detach-admin',
            'email' => 'detach-admin@example.com',
            'phone' => '76666666',
        ]);
        $tenant = $this->createTenant('file-detach-a');
        $admin->tenants()->sync([$tenant->id]);

        tenancy()->initialize($tenant);
        $slider = Slider::query()->create([
            'title' => 'Attached Slider',
            'placement' => 'Website',
            'status' => 'Active',
        ]);
        $gallery = $slider->galleries()->create([
            'type' => 'thumbnail',
            'status' => 'Active',
            'name' => 'attached-slider.png',
        ]);
        $slider->forceFill(['image_id' => $gallery->id])->save();
        Storage::disk('slider')->put('attached-slider.png', 'slider-image');
        tenancy()->end();
        DB::purge('tenant');

        $this->actingAs($admin)
            ->withSession(['admin_selected_tenant_id' => $tenant->id])
            ->delete(route('admin.file-manager.destroy'), [
                'current_directory' => 'sliders',
                'path' => 'sliders/attached-slider.png',
                'entry_type' => 'file',
            ])
            ->assertRedirect(route('admin.file-manager.index', ['directory' => 'sliders']));

        tenancy()->initialize($tenant);
        $this->assertNull($slider->fresh()->image_id);
        $this->assertDatabaseMissing('galleries', [
            'id' => $gallery->id,
        ], 'tenant');
        tenancy()->end();
        DB::purge('tenant');

        $this->assertFalse(Storage::disk('slider')->exists('attached-slider.png'));
    }

    protected function createUser(array $attributes): User
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

    protected function createTenant(string $id): Tenant
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
        $this->fileManagerTenantPrefixes[] = $id;

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

    protected function tenantRoleId(Tenant $tenant, string $roleName, bool $resetTenancy = true): int
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
