<?php

use App\Http\Controllers\AuthenticatedSessionController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\BusinessController;
use App\Http\Controllers\CatalogItemController;
use App\Http\Controllers\CatalogItemImportController;
use App\Http\Controllers\CatalogMetaController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliveryMethodController;
use App\Http\Controllers\DeliveryOrderController;
use App\Http\Controllers\DeliveryScheduleController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeePayrollController;
use App\Http\Controllers\EmployeeProfileController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ModifierController;
use App\Http\Controllers\MyCommissionController;
use App\Http\Controllers\MyEmployeeRecordController;
use App\Http\Controllers\MyPayrollController;
use App\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\PayrollApprovalController;
use App\Http\Controllers\PosCashMovementController;
use App\Http\Controllers\PosCategoryController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\PosDrawerController;
use App\Http\Controllers\PosLaunchController;
use App\Http\Controllers\PosOpenOrderController;
use App\Http\Controllers\PosSaleController;
use App\Http\Controllers\PosSettingController;
use App\Http\Controllers\PosShiftController;
use App\Http\Controllers\PosSmsController;
use App\Http\Controllers\PosSmsHistoryController;
use App\Http\Controllers\PosSmsTemplateController;
use App\Http\Controllers\PosVariantCategoryController;
use App\Http\Controllers\PosZReportController;
use App\Http\Controllers\PrinterController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\PwaController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\RunPayrollController;
use App\Http\Controllers\SalesItemController;
use App\Http\Controllers\SalesOverviewController;
use App\Http\Controllers\SalesReceiptController;
use App\Http\Controllers\SalesShiftController;
use App\Http\Controllers\ShopBannerController;
use App\Http\Controllers\ShopOrderController;
use App\Http\Controllers\ShopPromotionController;
use App\Http\Controllers\ShopSettingController;
use App\Http\Controllers\StockAssignmentController;
use App\Http\Controllers\StockConversionController;
use App\Http\Controllers\StockTransferController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\TaxFeeController;
use App\Http\Controllers\VoucherController;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

Route::withoutMiddleware([
    EncryptCookies::class,
    AddQueuedCookiesToResponse::class,
    StartSession::class,
    ShareErrorsFromSession::class,
    PreventRequestForgery::class,
    HandleInertiaRequests::class,
])->group(function () {
    Route::get('/manifest.webmanifest', [PwaController::class, 'manifest'])->name('pwa.manifest');
    Route::get('/sw.js', [PwaController::class, 'serviceWorker'])->name('pwa.service_worker');
    Route::get('/offline', [PwaController::class, 'offline'])->name('pwa.offline');
});

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/checkout', [HomeController::class, 'checkout'])->name('shop.checkout.show');
Route::post('/checkout/quote', [HomeController::class, 'quote'])
    ->middleware('throttle:30,1')
    ->name('shop.checkout.quote');
Route::post('/checkout/voucher', [HomeController::class, 'applyVoucher'])
    ->middleware('throttle:30,1')
    ->name('shop.checkout.voucher');
Route::post('/checkout', [HomeController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('shop.checkout');
Route::get('/track', [HomeController::class, 'track'])
    ->middleware('throttle:30,1')
    ->name('shop.track');

Route::prefix('backoffice')->group(function () {
    Route::middleware('employee.guest')->group(function () {
        Route::get('/', [AuthenticatedSessionController::class, 'create'])->name('login');

        Route::post('/login', [AuthenticatedSessionController::class, 'store'])
            ->middleware('throttle:login')
            ->name('login.store');
    });

    Route::middleware('employee.auth')->group(function () {
        Route::get('/me/commissions', [MyCommissionController::class, 'index'])->name('me.commissions');
        Route::get('/me/payroll', [MyPayrollController::class, 'index'])->name('me.payroll');
        Route::post('/me/payroll', [MyPayrollController::class, 'store'])->name('me.payroll.store');
        Route::delete('/me/payroll/{payrollFiling}', [MyPayrollController::class, 'destroy'])->name('me.payroll.destroy');
        Route::get('/me/payrolls/{employeePayroll}/download', [MyPayrollController::class, 'download'])->name('me.payrolls.download');
        Route::get('/me/employee-201', [MyEmployeeRecordController::class, 'show'])->name('me.employee-201');
        Route::put('/me/employee-201', [MyEmployeeRecordController::class, 'update'])->name('me.employee-201.update');

        Route::get('/dashboard', [DashboardController::class, 'index'])
            ->middleware('employee.permission:dashboard.view')
            ->name('dashboard');

        Route::get('/reports/sales', [ReportController::class, 'sales'])
            ->middleware('employee.permission:sales.view')
            ->name('reports.sales');
        Route::get('/reports/sales/export', [ReportController::class, 'exportSales'])
            ->middleware('employee.permission:sales.view')
            ->name('reports.sales.export');
        Route::get('/reports/commissions', [ReportController::class, 'commissions'])
            ->middleware('employee.permission:dashboard.view')
            ->name('reports.commissions');
        Route::get('/reports/commissions/export', [ReportController::class, 'exportCommissions'])
            ->middleware('employee.permission:dashboard.view')
            ->name('reports.commissions.export');
        Route::get('/reports/profit', [ReportController::class, 'profit'])
            ->middleware('employee.permission:sales.view')
            ->name('reports.profit');
        Route::get('/reports/profit/export', [ReportController::class, 'exportProfit'])
            ->middleware('employee.permission:sales.view')
            ->name('reports.profit.export');
        Route::get('/reports/inventory', [ReportController::class, 'inventory'])
            ->middleware('employee.permission:inventory.view')
            ->name('reports.inventory');
        Route::get('/reports/inventory/export', [ReportController::class, 'exportInventory'])
            ->middleware('employee.permission:inventory.view')
            ->name('reports.inventory.export');
        Route::get('/reports/products', [ReportController::class, 'products'])
            ->middleware('employee.permission:sales.view')
            ->name('reports.products');
        Route::get('/reports/products/export', [ReportController::class, 'exportProducts'])
            ->middleware('employee.permission:sales.view')
            ->name('reports.products.export');
        Route::get('/reports/purchasing', [ReportController::class, 'purchasing'])
            ->middleware('employee.permission:inventory.purchase-orders')
            ->name('reports.purchasing');
        Route::get('/reports/purchasing/export', [ReportController::class, 'exportPurchasing'])
            ->middleware('employee.permission:inventory.purchase-orders')
            ->name('reports.purchasing.export');
        Route::get('/reports/staff', [ReportController::class, 'staff'])
            ->middleware('employee.permission:sales.view')
            ->name('reports.staff');
        Route::get('/reports/staff/export', [ReportController::class, 'exportStaff'])
            ->middleware('employee.permission:sales.view')
            ->name('reports.staff.export');
        Route::get('/reports/reconciliation', [ReportController::class, 'reconciliation'])
            ->middleware('employee.permission:sales.view')
            ->name('reports.reconciliation');
        Route::get('/reports/reconciliation/export', [ReportController::class, 'exportReconciliation'])
            ->middleware('employee.permission:sales.view')
            ->name('reports.reconciliation.export');
        Route::get('/reports/payables', [ReportController::class, 'payables'])
            ->middleware('employee.permission:inventory.purchase-orders')
            ->name('reports.payables');
        Route::get('/reports/payables/export', [ReportController::class, 'exportPayables'])
            ->middleware('employee.permission:inventory.purchase-orders')
            ->name('reports.payables.export');

        Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

        Route::middleware('employee.permission:business.manage')->group(function () {
            Route::get('/business', [BusinessController::class, 'edit'])->name('business.edit');
            Route::post('/business', [BusinessController::class, 'update'])->name('business.update');
            Route::post('/business/reset', [BusinessController::class, 'reset'])->name('business.reset');
        });

        Route::middleware('employee.permission:pos.access')->group(function () {
            Route::get('/pos', [PosLaunchController::class, 'index'])->name('pos.launch');
            Route::get('/pos/drawer', [PosDrawerController::class, 'show'])->name('pos.drawer');
            Route::get('/pos/register/{branch}', [PosController::class, 'index'])->name('pos.index');
            Route::get('/pos/register/{branch}/shop-orders', [PosController::class, 'shopOrders'])->name('pos.shop-orders');
            Route::get('/pos/register/{branch}/online-orders', [PosController::class, 'onlineOrders'])->name('pos.online-orders');
            Route::put('/pos/register/{branch}/online-orders/{pos_open_order}', [PosController::class, 'updateOnlineOrder'])->name('pos.online-orders.update');
            Route::post('/pos/register/{branch}/online-orders/{pos_open_order}/sale', [PosController::class, 'createOnlineOrderSale'])->name('pos.online-orders.sale');
            Route::post('/pos/register/{branch}/shifts', [PosShiftController::class, 'store'])->name('pos.shifts.store');
            Route::post('/pos/register/{branch}/shifts/{pos_shift}/close', [PosShiftController::class, 'close'])->name('pos.shifts.close');
            Route::get('/pos/register/{branch}/shifts/{pos_shift}/z-report', [PosZReportController::class, 'show'])->name('pos.shifts.z-report');
            Route::post('/pos/register/{branch}/sales/preview', [PosSaleController::class, 'preview'])->name('pos.sales.preview');
            Route::post('/pos/register/{branch}/sales', [PosSaleController::class, 'store'])->name('pos.sales.store');
            Route::get('/pos/register/{branch}/sales/{pos_sale}/receipt', [PosSaleController::class, 'receipt'])->name('pos.sales.receipt');
            Route::put('/pos/register/{branch}/printers/{printer}', [PrinterController::class, 'connect'])->name('pos.printers.connect');
            Route::post('/pos/register/{branch}/open-orders', [PosOpenOrderController::class, 'store'])->name('pos.open-orders.store');
            Route::put('/pos/register/{branch}/open-orders/{pos_open_order}', [PosOpenOrderController::class, 'update'])
                ->scopeBindings()
                ->name('pos.open-orders.update');
            Route::delete('/pos/register/{branch}/open-orders/{pos_open_order}', [PosOpenOrderController::class, 'destroy'])
                ->scopeBindings()
                ->name('pos.open-orders.destroy');
            Route::post('/pos/register/{branch}/cash-movements', [PosCashMovementController::class, 'store'])->name('pos.cash-movements.store');
            Route::post('/pos/register/{branch}/drawer-opens', [PosCashMovementController::class, 'openDrawer'])
                ->middleware('employee.permission:pos.open-drawer')
                ->name('pos.drawer-opens.store');
            Route::post('/pos/register/{branch}/sms', [PosSmsController::class, 'store'])->name('pos.sms.store');
            Route::post('/pos/sms/{sms_message}/resend', [PosSmsController::class, 'resend'])->name('pos.sms.resend');
            Route::get('/pos/register/{branch}/sms/history', [PosSmsController::class, 'history'])->name('pos.sms.history');
            Route::get('/pos/sms/templates', [PosSmsTemplateController::class, 'index'])->name('pos.sms.templates.index');
            Route::post('/pos/sms/templates', [PosSmsTemplateController::class, 'store'])->name('pos.sms.templates.store');
            Route::put('/pos/sms/templates/{sms_template}', [PosSmsTemplateController::class, 'update'])->name('pos.sms.templates.update');
            Route::delete('/pos/sms/templates/{sms_template}', [PosSmsTemplateController::class, 'destroy'])->name('pos.sms.templates.destroy');
        });

        Route::middleware('employee.permission:pos.sms-history')->group(function () {
            Route::get('/pos/sms-history', [PosSmsHistoryController::class, 'index'])->name('pos.sms-history.index');
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
            Route::get('/sms/templates', [PosSmsTemplateController::class, 'manage'])->name('sms.templates.index');
            Route::post('/sms/templates', [PosSmsTemplateController::class, 'store'])->name('sms.templates.store');
            Route::put('/sms/templates/{sms_template}', [PosSmsTemplateController::class, 'update'])->name('sms.templates.update');
            Route::delete('/sms/templates/{sms_template}', [PosSmsTemplateController::class, 'destroy'])->name('sms.templates.destroy');
            Route::put('/pos/order-texts/{sms_template}', [PosSettingController::class, 'updateOrderText'])->name('pos.order-texts.update');
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
                Route::post('catalog/bulk', [CatalogItemController::class, 'bulk'])
                    ->name('catalog.bulk');
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

            Route::middleware('employee.permission:shop.orders')->group(function () {
                Route::get('orders', [ShopOrderController::class, 'index'])->name('orders.index');
                Route::get('orders/preorders/export', [ShopOrderController::class, 'exportPreorders'])->name('orders.preorders.export');
                Route::post('delivery-schedules', [DeliveryScheduleController::class, 'store'])->name('delivery-schedules.store');
                Route::put('delivery-schedules/{delivery_schedule}', [DeliveryScheduleController::class, 'update'])->name('delivery-schedules.update');
                Route::put('orders/{pos_open_order}/delivery-schedule', [ShopOrderController::class, 'updateDeliverySchedule'])->name('orders.delivery-schedule.update');
                Route::get('orders/{pos_open_order}', [ShopOrderController::class, 'show'])->name('orders.show');
                Route::put('orders/{pos_open_order}', [ShopOrderController::class, 'update'])->name('orders.update');
            });

            Route::middleware('employee.permission:shop.settings')->group(function () {
                Route::get('settings', [ShopSettingController::class, 'index'])->name('settings.index');
                Route::get('banner', [ShopBannerController::class, 'index'])->name('banner.index');
                Route::post('banner', [ShopBannerController::class, 'update'])->name('banner.update');
                Route::get('promotion', [ShopPromotionController::class, 'index'])->name('promotion.index');
                Route::post('promotion', [ShopPromotionController::class, 'update'])->name('promotion.update');
                Route::post('tax-fees', [TaxFeeController::class, 'store'])->name('tax-fees.store');
                Route::put('tax-fees/{tax_fee}', [TaxFeeController::class, 'update'])->name('tax-fees.update');
                Route::delete('tax-fees/{tax_fee}', [TaxFeeController::class, 'destroy'])->name('tax-fees.destroy');
                Route::post('payment-methods', [PaymentMethodController::class, 'store'])->name('payment-methods.store');
                Route::put('payment-methods/{payment_method}', [PaymentMethodController::class, 'update'])->name('payment-methods.update');
                Route::delete('payment-methods/{payment_method}', [PaymentMethodController::class, 'destroy'])->name('payment-methods.destroy');
                Route::post('delivery-methods', [DeliveryMethodController::class, 'store'])->name('delivery-methods.store');
                Route::put('delivery-methods/{delivery_method}', [DeliveryMethodController::class, 'update'])->name('delivery-methods.update');
                Route::delete('delivery-methods/{delivery_method}', [DeliveryMethodController::class, 'destroy'])->name('delivery-methods.destroy');
                Route::post('vouchers', [VoucherController::class, 'store'])->name('vouchers.store');
                Route::put('vouchers/{voucher}', [VoucherController::class, 'update'])->name('vouchers.update');
                Route::delete('vouchers/{voucher}', [VoucherController::class, 'destroy'])->name('vouchers.destroy');
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
                Route::post('purchase-orders/{purchase_order}/close', [PurchaseOrderController::class, 'close'])
                    ->name('purchase-orders.close');
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
                Route::get('suppliers/{supplier}', [SupplierController::class, 'show'])->name('suppliers.show');
                Route::post('suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
                Route::put('suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
                Route::delete('suppliers/{supplier}', [SupplierController::class, 'destroy'])->name('suppliers.destroy');
            });
        });

        Route::middleware('employee.permission:staff.manage')->group(function () {
            Route::get('/staff', [EmployeeController::class, 'index'])->name('staff.index');
            Route::get('/staff/records', [EmployeeProfileController::class, 'index'])->name('staff.records.index');
            Route::get('/staff/approvals', [PayrollApprovalController::class, 'index'])->name('staff.approvals.index');
            Route::put('/staff/approvals/{payrollFiling}', [PayrollApprovalController::class, 'update'])->name('staff.approvals.update');
            Route::get('/staff/payroll', [RunPayrollController::class, 'show'])->name('staff.payroll.run');
            Route::get('/staff/payroll/prepare', [RunPayrollController::class, 'redirectToRun']);
            Route::post('/staff/payroll/prepare', [RunPayrollController::class, 'prepare'])->name('staff.payroll.prepare');
            Route::get('/staff/payroll/preview', [RunPayrollController::class, 'redirectToRun']);
            Route::post('/staff/payroll/preview', [RunPayrollController::class, 'preview'])->name('staff.payroll.preview');
            Route::post('/staff/payroll/finalize', [RunPayrollController::class, 'finalize'])->name('staff.payroll.finalize');
            Route::get('/staff/payroll/download', [RunPayrollController::class, 'download'])->name('staff.payroll.download-all');
            Route::get('/staff/holidays', [HolidayController::class, 'index'])->name('staff.holidays.index');
            Route::post('/staff/holidays', [HolidayController::class, 'store'])->name('staff.holidays.store');
            Route::post('/staff/holidays/import', [HolidayController::class, 'import'])->name('staff.holidays.import');
            Route::delete('/staff/holidays/{holiday}', [HolidayController::class, 'destroy'])->name('staff.holidays.destroy');
            Route::post('/staff', [EmployeeController::class, 'store'])->name('staff.store');
            Route::post('/staff/{employee}/record', [EmployeeProfileController::class, 'store'])->name('staff.record.store');
            Route::get('/staff/{employee}/record', [EmployeeProfileController::class, 'show'])->name('staff.record.show');
            Route::put('/staff/{employee}/record', [EmployeeProfileController::class, 'update'])->name('staff.record.update');
            Route::post('/staff/{employee}/payroll', [EmployeePayrollController::class, 'store'])->name('staff.payroll.store');
            Route::post('/staff/{employee}/payroll/finalize', [EmployeePayrollController::class, 'finalize'])->name('staff.payroll.finalize');
            Route::get('/staff/{employee}/payroll/download', [EmployeePayrollController::class, 'download'])->name('staff.payroll.download');
            Route::get('/staff/payrolls/{employeePayroll}/download', [EmployeePayrollController::class, 'downloadFinalized'])->name('staff.payrolls.download');
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
