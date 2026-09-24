<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\AddressController;
use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CurrencyController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\FileManagerController;
use App\Http\Controllers\Admin\GeneralSettingController;
use App\Http\Controllers\Admin\ItemController;
use App\Http\Controllers\Admin\ItemOptionController;
use App\Http\Controllers\Admin\ItemVariationController;
use App\Http\Controllers\Admin\PriceListController;
use App\Http\Controllers\Admin\PromotionController;
use App\Http\Controllers\Admin\PurchaseOrderController;
use App\Http\Controllers\Admin\RateIndexController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SecurityController;
use App\Http\Controllers\Admin\SliderController;
use App\Http\Controllers\Admin\TelegramNotificationSettingController;
use App\Http\Controllers\Admin\TenantContextController;
use App\Http\Controllers\Admin\TenantController;
use App\Http\Controllers\Admin\TenantUserController;
use App\Http\Controllers\Admin\UnitOfMeasureController;
use App\Http\Controllers\Admin\UomGroupController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\NextPortalAuthController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::prefix('next/auth')->name('next.auth.')->group(function () {
    Route::get('csrf', [NextPortalAuthController::class, 'csrf'])->name('csrf');
    Route::get('session', [NextPortalAuthController::class, 'session'])->name('session');
    Route::post('login', [NextPortalAuthController::class, 'login'])
        ->middleware('throttle:6,1')
        ->name('login');
    Route::post('logout', [NextPortalAuthController::class, 'logout'])->middleware('auth')->name('logout');
    Route::post('tenant-handoff', [NextPortalAuthController::class, 'createTenantHandoff'])
        ->middleware('auth')
        ->name('tenant-handoff.create');
    Route::get('tenant-handoff', [NextPortalAuthController::class, 'redeemTenantHandoff'])
        ->name('tenant-handoff.redeem');
});

Route::middleware(['admin.central'])->group(function () {
    Route::middleware('guest')->prefix('admin')->group(function () {
        Route::get('login', [LoginController::class, 'showAdminLoginForm'])->name('admin.login');
        Route::post('login', [LoginController::class, 'login'])->name('admin.login.store');
    });

    Route::post('admin/logout', [LoginController::class, 'logout'])
        ->middleware('auth')
        ->name('admin.logout');

    Route::post('admin/tenant-context', [TenantContextController::class, 'store'])
        ->middleware(['auth', 'admin.administrator'])
        ->name('admin.tenant-context.store');

    Route::delete('admin/tenant-context', [TenantContextController::class, 'destroy'])
        ->middleware(['auth', 'admin.administrator'])
        ->name('admin.tenant-context.destroy');

    Route::get('admin/dashboard', [HomeController::class, 'index'])
        ->middleware(['auth', 'admin.tenancy'])
        ->name('admin.dashboard');

    Route::redirect('/home', '/admin/dashboard', 301)
        ->name('home');

    Route::middleware('auth')->prefix('admin')->name('admin.notifications.')->group(function () {
        Route::get('notifications', [\App\Http\Controllers\Admin\NotificationController::class, 'index'])->name('index');
        Route::get('notifications/open', [\App\Http\Controllers\Admin\NotificationController::class, 'open'])->name('open');
        Route::post('notifications/mark-read', [\App\Http\Controllers\Admin\NotificationController::class, 'markRead'])->name('mark-read');
        Route::post('notifications/mark-all-read', [\App\Http\Controllers\Admin\NotificationController::class, 'markAllRead'])->name('mark-all-read');
    });

    Route::middleware(['auth', 'admin.administrator'])->prefix('admin')->name('admin.')->group(function () {
        Route::resource('users', UserController::class)->except('show');
        Route::resource('tenants', TenantController::class)->except('show');
        Route::post('tenants/test-telegram', [TenantController::class, 'testTelegram'])->name('tenants.test-telegram');
        Route::get('security', [SecurityController::class, 'index'])->name('security.index');
        Route::put('security/settings', [SecurityController::class, 'updateSettings'])->name('security.settings.update');
        Route::post('security/block-ip', [SecurityController::class, 'blockIp'])->name('security.block-ip');
        Route::delete('security/unblock-ip/{id}', [SecurityController::class, 'unblockIp'])->name('security.unblock-ip');
        Route::post('security/quick-block/{id}', [SecurityController::class, 'quickBlockFromLog'])->name('security.quick-block');
        Route::delete('security/clear-logs', [SecurityController::class, 'clearLogs'])->name('security.clear-logs');
        Route::post('security/seed-samples', [SecurityController::class, 'seedSampleLogs'])->name('security.seed-samples');
        Route::post('security/test-telegram', [SecurityController::class, 'testTelegramAlert'])->name('security.test-telegram');
    });

    Route::middleware(['auth', 'admin.tenant', 'admin.tenancy'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
        Route::resource('addresses', AddressController::class)->except('show');
        Route::resource('currencies', CurrencyController::class)->except('show');
        Route::get('file-manager', [FileManagerController::class, 'index'])->name('file-manager.index');
        Route::post('file-manager', [FileManagerController::class, 'store'])->name('file-manager.store');
        Route::post('file-manager/folders', [FileManagerController::class, 'storeFolder'])->name('file-manager.folders.store');
        Route::delete('file-manager', [FileManagerController::class, 'destroy'])->name('file-manager.destroy');
        Route::get('general-settings', [GeneralSettingController::class, 'index'])->name('general-settings.index');
        Route::put('general-settings', [GeneralSettingController::class, 'update'])->name('general-settings.update');
        Route::post('general-settings/test-telegram', [GeneralSettingController::class, 'testTelegram'])->name('general-settings.test-telegram');
        Route::get('telegram-notifications', [TelegramNotificationSettingController::class, 'index'])->name('telegram-notifications.index');
        Route::put('telegram-notifications', [TelegramNotificationSettingController::class, 'update'])->name('telegram-notifications.update');
        Route::post('telegram-notifications/test', [TelegramNotificationSettingController::class, 'test'])->name('telegram-notifications.test');
        Route::get('rate-index', [RateIndexController::class, 'index'])->name('rate-index.index');
        Route::put('rate-index', [RateIndexController::class, 'update'])->name('rate-index.update');
        Route::resource('roles', RoleController::class)->except('show');
        Route::resource('tenant-users', TenantUserController::class)->except('show');
        Route::resource('branches', BranchController::class)->except('show');
        Route::resource('categories', CategoryController::class)->except('show');
        Route::resource('item-variations', ItemVariationController::class)->except('show');
        Route::resource('item-options', ItemOptionController::class)->except('show');
        Route::resource('uom-groups', UomGroupController::class)->except('show');
        Route::resource('units-of-measure', UnitOfMeasureController::class)->except('show');
        Route::resource('customers', CustomerController::class)->except('show');
        Route::get('items/import-template', [ItemController::class, 'importTemplate'])->name('items.import-template');
        Route::post('items/import', [ItemController::class, 'import'])->name('items.import');
        Route::delete('items/{item}/gallery/{gallery}', [ItemController::class, 'destroyGallery'])
            ->name('items.gallery.destroy');
        Route::resource('items', ItemController::class)->except('show');
        Route::get('purchase-orders/search-items', [PurchaseOrderController::class, 'searchItems'])
            ->name('purchase-orders.search-items');
        Route::get('purchase-orders/rates', [PurchaseOrderController::class, 'getExchangeRates'])
            ->name('purchase-orders.rates');
        Route::post('purchase-orders/{purchase_order}/receive', [PurchaseOrderController::class, 'receive'])
            ->name('purchase-orders.receive');
        Route::post('purchase-orders/{purchase_order}/cancel', [PurchaseOrderController::class, 'cancel'])
            ->name('purchase-orders.cancel');
        Route::post('purchase-orders/{purchase_order}/mark-ordered', [PurchaseOrderController::class, 'markOrdered'])
            ->name('purchase-orders.mark-ordered');
        Route::post('purchase-orders/{purchase_order}/mark-draft', [PurchaseOrderController::class, 'markDraft'])
            ->name('purchase-orders.mark-draft');
        Route::resource('purchase-orders', PurchaseOrderController::class);
        Route::post('promotions/{promotion}/lines', [PromotionController::class, 'storeLine'])->name('promotions.lines.store');
        Route::put('promotions/{promotion}/lines/{line}', [PromotionController::class, 'updateLine'])->name('promotions.lines.update');
        Route::delete('promotions/{promotion}/lines/{line}', [PromotionController::class, 'destroyLine'])->name('promotions.lines.destroy');
        Route::resource('promotions', PromotionController::class)->except('show');
        Route::post('price-lists/{price_list}/lines', [PriceListController::class, 'storeLine'])->name('price-lists.lines.store');
        Route::put('price-lists/{price_list}/lines/{line}', [PriceListController::class, 'updateLine'])->name('price-lists.lines.update');
        Route::delete('price-lists/{price_list}/lines/{line}', [PriceListController::class, 'destroyLine'])->name('price-lists.lines.destroy');
        Route::resource('price-lists', PriceListController::class)->except('show');
        Route::resource('sliders', SliderController::class)->except('show');

        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('/', [ReportController::class, 'sales'])->name('index');
            Route::get('sales', [ReportController::class, 'sales'])->name('sales');
            Route::get('sales/export', [ReportController::class, 'salesExport'])->name('sales.export');
            Route::get('sales/{sale}', [ReportController::class, 'saleDetail'])->name('sales.detail');
            Route::get('sales/{sale}/print', [ReportController::class, 'salePrint'])->name('sales.print');
            Route::get('products', [ReportController::class, 'products'])->name('products');
            Route::get('products/export', [ReportController::class, 'productsExport'])->name('products.export');
            Route::get('inventory', [ReportController::class, 'inventory'])->name('inventory');
            Route::get('inventory/export', [ReportController::class, 'inventoryExport'])->name('inventory.export');
            Route::get('payments', [ReportController::class, 'payments'])->name('payments');
        });
    });
});
