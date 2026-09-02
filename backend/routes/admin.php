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
use App\Http\Controllers\Admin\PosController;
use App\Http\Controllers\Admin\PriceListController;
use App\Http\Controllers\Admin\PromotionController;
use App\Http\Controllers\Admin\RateIndexController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SliderController;
use App\Http\Controllers\Admin\TenantContextController;
use App\Http\Controllers\Admin\TenantController;
use App\Http\Controllers\Admin\TenantUserController;
use App\Http\Controllers\Admin\UnitOfMeasureController;
use App\Http\Controllers\Admin\UomGroupController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

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

Route::get('/home', [HomeController::class, 'index'])
    ->middleware('auth')
    ->name('home');

Route::middleware(['auth', 'admin.administrator'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('users', UserController::class)->except('show');
    Route::resource('tenants', TenantController::class)->except('show');
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
    Route::delete('items/{item}/gallery/{gallery}', [ItemController::class, 'destroyGallery'])->name('items.gallery.destroy');
    Route::resource('items', ItemController::class)->except('show');
    Route::get('pos', [PosController::class, 'index'])->name('pos.index');
    Route::post('pos/price', [PosController::class, 'price'])->name('pos.price');
    Route::post('promotions/{promotion}/lines', [PromotionController::class, 'storeLine'])->name('promotions.lines.store');
    Route::put('promotions/{promotion}/lines/{line}', [PromotionController::class, 'updateLine'])->name('promotions.lines.update');
    Route::delete('promotions/{promotion}/lines/{line}', [PromotionController::class, 'destroyLine'])->name('promotions.lines.destroy');
    Route::resource('promotions', PromotionController::class)->except('show');
    Route::post('price-lists/{price_list}/lines', [PriceListController::class, 'storeLine'])->name('price-lists.lines.store');
    Route::put('price-lists/{price_list}/lines/{line}', [PriceListController::class, 'updateLine'])->name('price-lists.lines.update');
    Route::delete('price-lists/{price_list}/lines/{line}', [PriceListController::class, 'destroyLine'])->name('price-lists.lines.destroy');
    Route::resource('price-lists', PriceListController::class)->except('show');
    Route::resource('sliders', SliderController::class)->except('show');
});
