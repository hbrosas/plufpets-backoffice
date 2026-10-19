<?php

use App\Http\Controllers\AuthenticatedSessionController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CatalogItemController;
use App\Http\Controllers\CatalogItemImportController;
use App\Http\Controllers\CatalogMetaController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliveryMethodController;
use App\Http\Controllers\DeliveryOrderController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ModifierController;
use App\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\PosCashMovementController;
use App\Http\Controllers\PosCategoryController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\PosDrawerController;
use App\Http\Controllers\PosOpenOrderController;
use App\Http\Controllers\PosSaleController;
use App\Http\Controllers\PosSettingController;
use App\Http\Controllers\PosShiftController;
use App\Http\Controllers\PosVariantCategoryController;
use App\Http\Controllers\PosZReportController;
use App\Http\Controllers\PrinterController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SalesItemController;
use App\Http\Controllers\SalesOverviewController;
use App\Http\Controllers\SalesReceiptController;
use App\Http\Controllers\SalesShiftController;
use App\Http\Controllers\ShopSettingController;
use App\Http\Controllers\StockAssignmentController;
use App\Http\Controllers\StockConversionController;
use App\Http\Controllers\StockTransferController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\TaxFeeController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::prefix('backoffice')->group(function () {
    Route::middleware('employee.guest')->group(function () {
        Route::get('/', [AuthenticatedSessionController::class, 'create'])->name('login');

        Route::post('/login', [AuthenticatedSessionController::class, 'store'])
            ->middleware('throttle:login')
            ->name('login.store');
    });

    Route::middleware('employee.auth')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

        Route::middleware('employee.permission:pos.access')->group(function () {
            Route::get('/pos/drawer', [PosDrawerController::class, 'show'])->name('pos.drawer');
            Route::get('/pos/register/{branch}', [PosController::class, 'index'])->name('pos.index');
            Route::post('/pos/register/{branch}/shifts', [PosShiftController::class, 'store'])->name('pos.shifts.store');
            Route::post('/pos/register/{branch}/shifts/{pos_shift}/close', [PosShiftController::class, 'close'])->name('pos.shifts.close');
            Route::get('/pos/register/{branch}/shifts/{pos_shift}/z-report', [PosZReportController::class, 'show'])->name('pos.shifts.z-report');
            Route::post('/pos/register/{branch}/sales', [PosSaleController::class, 'store'])->name('pos.sales.store');
            Route::post('/pos/register/{branch}/open-orders', [PosOpenOrderController::class, 'store'])->name('pos.open-orders.store');
            Route::put('/pos/register/{branch}/open-orders/{pos_open_order}', [PosOpenOrderController::class, 'update'])
                ->scopeBindings()
                ->name('pos.open-orders.update');
            Route::delete('/pos/register/{branch}/open-orders/{pos_open_order}', [PosOpenOrderController::class, 'destroy'])
                ->scopeBindings()
                ->name('pos.open-orders.destroy');
            Route::post('/pos/register/{branch}/cash-movements', [PosCashMovementController::class, 'store'])->name('pos.cash-movements.store');
        });

        Route::middleware('employee.permission:pos.categories')->group(function () {
            Route::get('/pos/categories', [PosCategoryController::class, 'index'])->name('pos.categories.index');
            Route::post('/pos/categories', [PosCategoryController::class, 'store'])->name('pos.categories.store');
            Route::put('/pos/categories/{pos_category}', [PosCategoryController::class, 'update'])->name('pos.categories.update');
            Route::delete('/pos/categories/{pos_category}', [PosCategoryController::class, 'destroy'])->name('pos.categories.destroy');
            Route::put('/pos/variants/{catalog_item_variant}/category', [PosVariantCategoryController::class, 'update'])
                ->name('pos.variants.category');
        });

        Route::middleware('employee.permission:pos.settings')->group(function () {
            Route::get('/pos/settings', [PosSettingController::class, 'index'])->name('pos.settings.index');
            Route::post('/pos/printers', [PrinterController::class, 'store'])->name('pos.printers.store');
            Route::put('/pos/printers/{printer}', [PrinterController::class, 'update'])->name('pos.printers.update');
            Route::delete('/pos/printers/{printer}', [PrinterController::class, 'destroy'])->name('pos.printers.destroy');
            Route::get('/pos/printers/{printer}/test', [PrinterController::class, 'test'])->name('pos.printers.test');
        });

        Route::prefix('sales')->name('sales.')->middleware('employee.permission:sales.view')->group(function () {
            Route::get('/', [SalesOverviewController::class, 'index'])->name('index');
            Route::get('receipts', [SalesReceiptController::class, 'index'])->name('receipts.index');
            Route::get('receipts/{pos_sale}', [SalesReceiptController::class, 'show'])->name('receipts.show');
            Route::post('receipts/{pos_sale}/refund', [SalesReceiptController::class, 'refund'])
                ->middleware('employee.permission:sales.refund')
                ->name('receipts.refund');
            Route::get('items', [SalesItemController::class, 'index'])->name('items.index');
            Route::get('shifts', [SalesShiftController::class, 'index'])->name('shifts.index');
        });

        Route::prefix('shop')->name('shop.')->group(function () {
            Route::middleware('employee.permission:shop.catalog')->group(function () {
                Route::get('catalog/import/template', [CatalogItemImportController::class, 'template'])
                    ->name('catalog.import.template');
                Route::post('catalog/import', [CatalogItemImportController::class, 'store'])
                    ->middleware('throttle:catalog-import')
                    ->name('catalog.import.store');
                Route::get('catalog/{catalog_item}/supplies/{supply}/labels', [CatalogItemController::class, 'labels'])
                    ->name('catalog.supplies.labels');
                Route::resource('catalog', CatalogItemController::class)
                    ->parameters(['catalog' => 'catalog_item']);
            });

            Route::middleware('employee.permission:shop.catalog-meta')->group(function () {
                Route::get('catalog-meta', [CatalogMetaController::class, 'index'])->name('catalog-meta.index');
                Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
                Route::put('categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
                Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
                Route::post('brands', [BrandController::class, 'store'])->name('brands.store');
                Route::put('brands/{brand}', [BrandController::class, 'update'])->name('brands.update');
                Route::delete('brands/{brand}', [BrandController::class, 'destroy'])->name('brands.destroy');
            });

            Route::middleware('employee.permission:shop.modifiers')->group(function () {
                Route::get('modifiers', [ModifierController::class, 'index'])->name('modifiers.index');
                Route::post('modifiers', [ModifierController::class, 'store'])->name('modifiers.store');
                Route::put('modifiers/{modifier}', [ModifierController::class, 'update'])->name('modifiers.update');
                Route::delete('modifiers/{modifier}', [ModifierController::class, 'destroy'])->name('modifiers.destroy');
            });

            Route::middleware('employee.permission:shop.branches')->group(function () {
                Route::resource('branches', BranchController::class)->except(['show']);
            });

            Route::middleware('employee.permission:shop.settings')->group(function () {
                Route::get('settings', [ShopSettingController::class, 'index'])->name('settings.index');
                Route::post('tax-fees', [TaxFeeController::class, 'store'])->name('tax-fees.store');
                Route::put('tax-fees/{tax_fee}', [TaxFeeController::class, 'update'])->name('tax-fees.update');
                Route::delete('tax-fees/{tax_fee}', [TaxFeeController::class, 'destroy'])->name('tax-fees.destroy');
                Route::post('payment-methods', [PaymentMethodController::class, 'store'])->name('payment-methods.store');
                Route::put('payment-methods/{payment_method}', [PaymentMethodController::class, 'update'])->name('payment-methods.update');
                Route::delete('payment-methods/{payment_method}', [PaymentMethodController::class, 'destroy'])->name('payment-methods.destroy');
                Route::post('delivery-methods', [DeliveryMethodController::class, 'store'])->name('delivery-methods.store');
                Route::put('delivery-methods/{delivery_method}', [DeliveryMethodController::class, 'update'])->name('delivery-methods.update');
                Route::delete('delivery-methods/{delivery_method}', [DeliveryMethodController::class, 'destroy'])->name('delivery-methods.destroy');
            });
        });

        Route::prefix('inventory')->name('inventory.')->group(function () {
            Route::middleware('employee.permission:inventory.view')->group(function () {
                Route::get('/', [InventoryController::class, 'index'])->name('index');
                Route::get('products/{catalog_item_variant}/labels', [InventoryController::class, 'labels'])
                    ->name('products.labels');
                Route::get('products/{catalog_item_variant}', [InventoryController::class, 'show'])
                    ->name('products.show');
            });

            Route::middleware('employee.permission:inventory.adjust')->group(function () {
                Route::post('transfers', [StockTransferController::class, 'store'])->name('transfers.store');
                Route::post('conversions', [StockConversionController::class, 'store'])->name('conversions.store');
            });

            Route::middleware('employee.permission:inventory.purchase-orders')->group(function () {
                Route::post('purchase-orders/products', [PurchaseOrderController::class, 'storeProduct'])
                    ->name('purchase-orders.products.store');
                Route::get('purchase-orders/{purchase_order}/print', [PurchaseOrderController::class, 'print'])
                    ->name('purchase-orders.print');
                Route::post('purchase-orders/{purchase_order}/confirm', [PurchaseOrderController::class, 'confirm'])
                    ->name('purchase-orders.confirm');
                Route::resource('purchase-orders', PurchaseOrderController::class);
            });

            Route::middleware('employee.permission:inventory.delivery-orders')->group(function () {
                Route::get('delivery-orders', [DeliveryOrderController::class, 'index'])->name('delivery-orders.index');
                Route::get('delivery-orders/{purchase_order}', [DeliveryOrderController::class, 'show'])
                    ->name('delivery-orders.show');
                Route::post('delivery-orders/{purchase_order}/receive', [DeliveryOrderController::class, 'receive'])
                    ->name('delivery-orders.receive');
            });

            Route::middleware('employee.permission:inventory.stock-assignments')->group(function () {
                Route::get('stock-assignments', [StockAssignmentController::class, 'index'])->name('stock-assignments.index');
                Route::post('stock-assignments', [StockAssignmentController::class, 'store'])->name('stock-assignments.store');
            });

            Route::middleware('employee.permission:inventory.suppliers')->group(function () {
                Route::get('suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
                Route::post('suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
                Route::put('suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
                Route::delete('suppliers/{supplier}', [SupplierController::class, 'destroy'])->name('suppliers.destroy');
            });
        });

        Route::middleware('employee.permission:staff.manage')->group(function () {
            Route::get('/staff', [EmployeeController::class, 'index'])->name('staff.index');
            Route::post('/staff', [EmployeeController::class, 'store'])->name('staff.store');
            Route::put('/staff/{employee}', [EmployeeController::class, 'update'])->name('staff.update');
            Route::delete('/staff/{employee}', [EmployeeController::class, 'destroy'])->name('staff.destroy');
        });

        Route::middleware('employee.permission:roles.manage')->group(function () {
            Route::get('/staff/roles', [RoleController::class, 'index'])->name('staff.roles.index');
            Route::post('/staff/roles', [RoleController::class, 'store'])->name('staff.roles.store');
            Route::put('/staff/roles/{role}', [RoleController::class, 'update'])->name('staff.roles.update');
            Route::delete('/staff/roles/{role}', [RoleController::class, 'destroy'])->name('staff.roles.destroy');
        });
    });
});
