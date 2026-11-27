<?php

use App\Http\Controllers\Admin\AdminPageController;
use App\Http\Controllers\Admin\Api\AnalyticsApiController;
use App\Http\Controllers\Admin\Api\DashboardApiController;
use App\Http\Controllers\Admin\Api\OrderApiController;
use App\Http\Controllers\Admin\Api\ProductApiController;
use App\Http\Controllers\Admin\AttendanceController;
use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Admin\CafeUserController;
use App\Http\Controllers\Admin\CashRegisterController;
use App\Http\Controllers\Admin\CentralKitchenController;
use App\Http\Controllers\Admin\CustomDomainController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\HppCalculatorController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\InventoryControlsController;
use App\Http\Controllers\Admin\KitchenController;
use App\Http\Controllers\Admin\LocationInventoryController;
use App\Http\Controllers\Admin\McpConnectionController;
use App\Http\Controllers\Admin\ModifierController;
use App\Http\Controllers\Admin\OnboardingController;
use App\Http\Controllers\Admin\OwnerDashboardController;
use App\Http\Controllers\Admin\PosController;
use App\Http\Controllers\Admin\PurchaseOrderController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ShiftController;
use App\Http\Controllers\Admin\VoucherController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\SuperAdmin\UserController;
use App\Http\Controllers\SuperAdminController;
use App\Http\Middleware\ResolveBranch;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function (Request $request) {
    if ($request->attributes->has('custom_domain_shop')) {
        return app(ShopController::class)->showCustomDomain($request);
    }

    $dashboardUrl = null;
    if (auth()->check()) {
        $dashboardUrl = route(auth()->user()->homeRoute());
    }

    return view('landing', compact('dashboardUrl'));
})->name('landing');

Route::get('/harga', function () {
    return view('harga');
})->name('harga');

Route::get('/admin/owner/dashboard', OwnerDashboardController::class)
    ->middleware(['auth', 'schema:branches,orders'])->name('admin.owner.dashboard');

Route::middleware('auth')->controller(McpConnectionController::class)->group(function () {
    Route::get('/admin/integrations/mcp', 'index')->name('admin.integrations.mcp');
    Route::post('/admin/integrations/mcp', 'store')->name('admin.integrations.mcp.store');
    Route::post('/admin/integrations/mcp/{connection}/reveal', 'reveal')->name('admin.integrations.mcp.reveal');
    Route::delete('/admin/integrations/mcp/{connection}', 'destroy')->name('admin.integrations.mcp.destroy');
});

Route::middleware('auth')->controller(CustomDomainController::class)->group(function (): void {
    Route::get('/admin/custom-domain', 'index')->name('admin.custom-domain.index');
    Route::post('/admin/custom-domain', 'store')->name('admin.custom-domain.store');
    Route::post('/admin/custom-domain/{domain}/verify', 'verify')->whereNumber('domain')->name('admin.custom-domain.verify');
    Route::delete('/admin/custom-domain/{domain}', 'destroy')->whereNumber('domain')->name('admin.custom-domain.destroy');
});

Route::middleware(['auth', 'schema:branches'])->prefix('admin/branches')->name('admin.branches.')->group(function () {
    Route::get('/', [BranchController::class, 'index'])->name('index');
    Route::post('/', [BranchController::class, 'store'])->name('store');
    Route::put('/{branch}/qris', [BranchController::class, 'updateStaticQris'])->whereNumber('branch')->middleware('schema:static_qris')->name('qris.update');
    Route::delete('/{branch}/qris', [BranchController::class, 'removeStaticQris'])->whereNumber('branch')->middleware('schema:static_qris')->name('qris.destroy');
    Route::get('/{branch}/staff', [BranchController::class, 'staff'])->name('staff');
    Route::post('/{branch}/staff', [BranchController::class, 'assign'])->name('assign');
    Route::delete('/{branch}/staff/{user}', [BranchController::class, 'unassign'])->name('unassign');
});

$operatingRoutes = function (): void {
    // Onboarding
    Route::middleware(['can:manage-settings', 'schema:settings'])->group(function () {
        Route::get('/admin/onboarding', [OnboardingController::class, 'index'])->name('admin.onboarding');
        Route::post('/admin/onboarding/step', [OnboardingController::class, 'updateStep'])->name('admin.onboarding.step');
        Route::post('/admin/onboarding/complete', [OnboardingController::class, 'complete'])->name('admin.onboarding.complete');
    });

    Route::get('/admin/attendance', [AttendanceController::class, 'index'])->middleware('schema:attendance')->name('admin.attendance.index');
    Route::post('/admin/attendance', [AttendanceController::class, 'store'])->middleware('schema:attendance')->middleware('throttle:20,1')->name('admin.attendance.store');
    Route::put('/admin/attendance/location', [AttendanceController::class, 'updateLocationSettings'])->middleware('schema:attendance')->name('admin.attendance.location');
    Route::get('/admin/attendance/report', [AttendanceController::class, 'report'])->middleware('schema:attendance')->name('admin.attendance.report');

    // Dashboard (accessible to all authenticated users)
    Route::get('/admin/dashboard', [AdminPageController::class, 'index'])->name('admin.dashboard');
    foreach (config('admin_pages') as $page => $definition) {
        if (in_array($page, ['dashboard', 'report'], true)) {
            continue;
        }
        Route::get('/admin/'.$definition['uri'], [AdminPageController::class, 'index'])
            ->defaults('page', $page)->middleware(['can:'.$definition['ability'], 'schema:'.$definition['schema']])->name($definition['route']);
    }

    Route::middleware(['can:manage-users', 'schema:users'])->group(function () {
        Route::get('/admin/users', [CafeUserController::class, 'index'])->name('admin.users.index');
        Route::get('/admin/users/create', [CafeUserController::class, 'create'])->name('admin.users.create');
        Route::post('/admin/users', [CafeUserController::class, 'store'])->name('admin.users.store');
        Route::get('/admin/users/{cafeUser}/edit', [CafeUserController::class, 'edit'])->whereNumber('cafeUser')->name('admin.users.edit');
        Route::put('/admin/users/{cafeUser}', [CafeUserController::class, 'update'])->whereNumber('cafeUser')->name('admin.users.update');
        Route::delete('/admin/users/{cafeUser}', [CafeUserController::class, 'destroy'])->whereNumber('cafeUser')->middleware('schema:user_archive,attendance,register')->name('admin.users.destroy');
        Route::patch('/admin/users/{cafeUser}/deactivate', [CafeUserController::class, 'deactivate'])->whereNumber('cafeUser')->name('admin.users.deactivate');
        Route::post('/admin/api/crew', [CafeUserController::class, 'store']);
        Route::put('/admin/api/crew/{id}', [CafeUserController::class, 'update'])->whereNumber('id');
        Route::delete('/admin/api/crew/{id}', [CafeUserController::class, 'deactivate'])->whereNumber('id');
    });

    // POS & Orders
    // POS Terminal + APIs
    Route::middleware(['can:access-pos', 'schema:pos'])->group(function () {
        Route::get('/admin/pos', [PosController::class, 'index'])->name('admin.pos.index');
        Route::get('/admin/pos/customer-display', [PosController::class, 'customerDisplay'])->name('admin.pos.customer-display');
        Route::get('/admin/pos/customer-display/sync', [PosController::class, 'customerDisplaySnapshot'])->name('admin.pos.customer-display.snapshot');
        Route::post('/admin/pos/customer-display/sync', [PosController::class, 'syncCustomerDisplay'])->name('admin.pos.customer-display.sync');
        Route::get('/admin/pos/customer-kiosk', [PosController::class, 'customerKiosk'])->name('admin.pos.customer-kiosk');
        Route::post('/admin/pos/customer-kiosk/action', [PosController::class, 'customerKioskAction'])->name('admin.pos.customer-kiosk.action');
        Route::post('/admin/pos/orders', [PosController::class, 'submitOrder'])->middleware(['can:manage-payments', 'schema:checkout'])->name('admin.pos.orders.submit');
        Route::get('/admin/pos/static-qris', [PosController::class, 'staticQrisOrders'])->middleware(['can:manage-payments', 'schema:checkout,static_qris'])->name('admin.pos.qris.index');
        Route::post('/admin/pos/static-qris/{order}/confirm', [PosController::class, 'confirmStaticQris'])->whereNumber('order')->middleware(['can:manage-payments', 'schema:checkout,static_qris'])->name('admin.pos.qris.confirm');
        Route::post('/admin/pos/orders/hold', [PosController::class, 'holdOrder'])->middleware('schema:checkout')->name('admin.pos.orders.hold');
        Route::get('/admin/pos/orders/held', [PosController::class, 'heldOrders'])->name('admin.pos.orders.held');
        Route::post('/admin/pos/orders/{order}/recall', [PosController::class, 'recallOrder'])->name('admin.pos.orders.recall');
        Route::delete('/admin/pos/orders/{order}/hold', [PosController::class, 'cancelHeldOrder'])->name('admin.pos.orders.hold.cancel');
        Route::get('/admin/pos/products', [PosController::class, 'products'])->name('admin.pos.products');
        Route::post('/admin/pos/vouchers/validate', [VoucherController::class, 'validateCode'])->name('admin.pos.vouchers.validate');
        Route::post('/admin/pos/discount-settings', [PosController::class, 'saveDiscountSettings'])->middleware('can:manage-settings')->name('admin.pos.discount-settings.save');
    });

    Route::get('/admin/api/orders/live', [DashboardController::class, 'getLiveOrders'])->middleware(['can:view-orders', 'schema:orders']);
    Route::post('/admin/api/orders/{order}/status', [DashboardController::class, 'updateOrderStatus'])->middleware(['can:manage-orders', 'schema:orders,location_inventory']);
    Route::post('/admin/api/orders/{order}/batch-status', [DashboardController::class, 'updateBatchStatus'])->middleware(['can:manage-orders', 'schema:orders,location_inventory']);
    Route::get('/admin/api/orders/sample/test-print', [DashboardController::class, 'testPrint'])->middleware(['can:manage-settings']);
    Route::get('/admin/api/orders/{order}/print', [DashboardController::class, 'printOrder'])->middleware(['can:view-orders', 'schema:orders']);

    // Cash Register / Shift Session
    Route::get('/admin/cash-reconciliation', [CashRegisterController::class, 'index'])->middleware(['can:manage-register', 'schema:register'])->name('admin.register.index');
    Route::post('/shift/open', [CashRegisterController::class, 'openShift'])->middleware(['can:manage-register', 'schema:register'])->name('shift.open');
    Route::post('/shift/close', [CashRegisterController::class, 'closeShift'])->middleware(['can:manage-register', 'schema:register'])->name('shift.close');
    Route::post('/shift/cash-out', [CashRegisterController::class, 'recordCashOut'])->middleware(['can:manage-register', 'schema:register'])->name('shift.cash-out');

    Route::middleware(['can:manage-hpp', 'schema:hpp'])->group(function () {
        Route::get('/admin/hpp', [HppCalculatorController::class, 'index'])->name('admin.hpp.index');
        Route::post('/admin/hpp/materials', [HppCalculatorController::class, 'storeMaterial'])->name('admin.hpp.materials.store');
        Route::post('/admin/hpp/calculate', [HppCalculatorController::class, 'calculate'])->name('admin.hpp.calculate');
        Route::put('/admin/hpp/products/{product}', [HppCalculatorController::class, 'update'])->whereNumber('product')->name('admin.hpp.update');
    });

    // Menu Management
    Route::middleware(['can:manage-menu', 'schema:catalog,inventory,audit'])->group(function () {
        Route::get('/admin/menu/template/excel', [DashboardController::class, 'downloadMenuExcelTemplate'])->name('admin.menu.template.excel');
        Route::get('/admin/menu/template/csv', [DashboardController::class, 'downloadMenuCsvTemplate'])->name('admin.menu.template.csv');
        Route::post('/admin/api/menu/{menu}/toggle', [DashboardController::class, 'toggleMenuStatus']);
        Route::post('/admin/api/menu/{menu}/toggle-discount', [DashboardController::class, 'toggleMenuDiscount']);
        Route::post('/admin/api/menu/{menu}/branch-override', [DashboardController::class, 'saveBranchProductOverride'])->name('admin.menu.branch-override.save');
        Route::delete('/admin/api/menu/{menu}/branch-override', [DashboardController::class, 'resetBranchProductOverride'])->name('admin.menu.branch-override.reset');
        Route::post('/admin/api/menu/bulk', [DashboardController::class, 'saveMenuBulk']);
        Route::post('/admin/api/menu', [DashboardController::class, 'saveMenu']);
        Route::delete('/admin/api/menu/{id}', [DashboardController::class, 'deleteMenu']);
        Route::post('/admin/api/category', [DashboardController::class, 'saveCategory']);
        Route::put('/admin/api/category/{id}', [DashboardController::class, 'updateCategory']);
        Route::delete('/admin/api/category/{id}', [DashboardController::class, 'deleteCategory']);

        // Add-ons / Modifiers Management
        Route::get('/admin/api/addons', [ModifierController::class, 'index'])->name('admin.addons.api.index');
        Route::post('/admin/api/addons', [ModifierController::class, 'store'])->name('admin.addons.store');
        Route::put('/admin/api/addons/{id}', [ModifierController::class, 'update'])->whereNumber('id')->name('admin.addons.update');
        Route::delete('/admin/api/addons/{id}', [ModifierController::class, 'destroy'])->whereNumber('id')->name('admin.addons.destroy');
        Route::patch('/admin/api/addons/{id}/toggle', [ModifierController::class, 'toggle'])->whereNumber('id')->name('admin.addons.toggle');

    });

    Route::middleware(['can:view-inventory-controls', 'schema:inventory_controls'])->prefix('admin/inventory/controls')->name('admin.controls.')->group(function () {
        Route::get('/', [InventoryControlsController::class, 'index'])->name('index');
        Route::get('/variance', [InventoryControlsController::class, 'report'])->middleware('can:view-inventory-variance')->name('variance');
        Route::post('/requests', [InventoryControlsController::class, 'requestStock'])->middleware('can:record-inventory-controls')->name('requests.store');
        Route::post('/requests/{id}/review', [InventoryControlsController::class, 'reviewRequest'])->whereNumber('id')->middleware('can:approve-inventory-controls')->name('requests.review');
        Route::post('/requests/{id}/dispatch', [InventoryControlsController::class, 'dispatch'])->whereNumber('id')->middleware('can:dispatch-stock')->name('requests.dispatch');
        Route::post('/counts', [InventoryControlsController::class, 'recordCount'])->middleware('can:record-inventory-controls')->name('counts.store');
        Route::post('/counts/{id}/review', [InventoryControlsController::class, 'reviewCount'])->whereNumber('id')->middleware('can:approve-inventory-controls')->name('counts.review');
        Route::post('/purchases', [InventoryControlsController::class, 'recordPurchase'])->middleware('can:record-inventory-controls')->name('purchases.store');
        Route::post('/purchases/{id}/review', [InventoryControlsController::class, 'reviewPurchase'])->whereNumber('id')->middleware('can:approve-inventory-controls')->name('purchases.review');
        Route::post('/purchases/{id}/receive', [InventoryControlsController::class, 'receivePurchase'])->whereNumber('id')->middleware('can:record-inventory-controls')->name('purchases.receive');
        Route::get('/purchases/{id}/proof', [InventoryControlsController::class, 'proof'])->whereNumber('id')->name('purchases.proof');
    });

    Route::middleware(['can:view-production', 'schema:production'])->prefix('admin/central-kitchen')->name('admin.production.')->group(function () {
        Route::get('/', [CentralKitchenController::class, 'index'])->name('index');
        Route::get('/work-orders/{id}', [CentralKitchenController::class, 'show'])->whereNumber('id')->name('show');
        Route::post('/recipes', [CentralKitchenController::class, 'recipe'])->middleware('can:manage-production-recipes')->name('recipe');
        Route::post('/work-orders', [CentralKitchenController::class, 'workOrder'])->middleware('can:manage-production-orders')->name('work-order');
        Route::post('/work-orders/{id}/result', [CentralKitchenController::class, 'result'])->whereNumber('id')->middleware('can:manage-production-orders')->name('result');
        Route::post('/work-orders/{id}/post', [CentralKitchenController::class, 'post'])->whereNumber('id')->middleware('can:post-production')->name('post');
    });

    Route::middleware(['can:view-purchases', 'schema:purchasing'])->prefix('admin/purchases')->name('admin.purchases.')->group(function () {
        Route::get('/', [PurchaseOrderController::class, 'index'])->name('index');
        Route::get('/{id}', [PurchaseOrderController::class, 'show'])->whereNumber('id')->name('show');
        Route::post('/suppliers', [PurchaseOrderController::class, 'addSupplier'])->middleware('can:manage-purchases')->name('suppliers.store');
        Route::post('/', [PurchaseOrderController::class, 'store'])->middleware('can:manage-purchases')->name('store');
        Route::post('/{id}/approve', [PurchaseOrderController::class, 'approve'])->whereNumber('id')->middleware('can:approve-purchases')->name('approve');
        Route::post('/{id}/receipts', [PurchaseOrderController::class, 'receive'])->whereNumber('id')->middleware('can:receive-purchases')->name('receive');
    });

    Route::middleware(['can:manage-inventory', 'schema:inventory,location_inventory'])->prefix('admin/inventory/locations')->name('admin.inventory.locations.')->group(function () {
        Route::get('/', [LocationInventoryController::class, 'index'])->name('index');
        Route::post('/warehouse-stock', [LocationInventoryController::class, 'stock'])->middleware('can:dispatch-stock')->name('stock');
        Route::post('/transfers', [LocationInventoryController::class, 'send'])->middleware('can:dispatch-stock')->name('send');
        Route::post('/transfers/{id}/receive', [LocationInventoryController::class, 'receive'])->whereNumber('id')->middleware('can:receive-stock')->name('receive');
        Route::post('/modifier-recipes', [LocationInventoryController::class, 'saveModifierRecipe'])->middleware('can:manage-hpp')->name('modifier-recipe');
    });

    Route::middleware(['can:manage-inventory', 'schema:inventory'])->group(function () {
        // Inventory & Recipes Management
        Route::get('/admin/api/inventory', [InventoryController::class, 'index']);
        Route::post('/admin/api/inventory/units', [InventoryController::class, 'addCustomUnit'])->middleware('can:manage-inventory-master');
        Route::post('/admin/api/inventory/material', [InventoryController::class, 'saveMaterial'])->middleware('can:manage-inventory-master');
        Route::delete('/admin/api/inventory/material/{id}', [InventoryController::class, 'deleteMaterial'])->middleware('can:manage-inventory-master');
        Route::post('/admin/api/inventory/materials/bulk-delete', [InventoryController::class, 'bulkDeleteMaterials'])->middleware('can:manage-inventory-master');
        Route::post('/admin/api/inventory/adjust', [InventoryController::class, 'adjustStock']);
        Route::get('/admin/api/inventory/recipes/{productId}', [InventoryController::class, 'getProductRecipes']);
        Route::post('/admin/api/inventory/recipes/{productId}', [InventoryController::class, 'saveProductRecipes'])->middleware('can:manage-inventory-master');
    });

    Route::get('/admin/api/dashboard/summary', [DashboardApiController::class, 'getSummary'])->middleware(['can:view-dashboard', 'schema:summary']);
    Route::get('/admin/api/dashboard/orders', [OrderApiController::class, 'index'])->middleware(['can:view-orders', 'schema:orders']);
    Route::get('/admin/api/dashboard/products', [ProductApiController::class, 'index'])->middleware(['can:manage-menu', 'schema:catalog']);
    Route::get('/admin/api/dashboard/analytics', [AnalyticsApiController::class, 'index'])->middleware(['can:view-reports', 'schema:analytics']);
    Route::post('/admin/api/table', [DashboardController::class, 'saveTable'])->middleware(['can:manage-branch-tables', 'schema:settings']);
    Route::put('/admin/api/table', [DashboardController::class, 'updateTableQR'])->middleware(['can:manage-branch-tables', 'schema:settings']);

    // Settings
    Route::middleware(['can:manage-settings', 'schema:settings'])->group(function () {

        Route::post('/admin/api/settings', [DashboardController::class, 'saveSettings'])->name('admin.settings.save');
        Route::post('/admin/api/settings/reset-branch', [DashboardController::class, 'resetBranchSettings'])->name('admin.settings.reset-branch');
        Route::post('/admin/api/operating-hours', [DashboardController::class, 'saveOperatingHours'])->name('admin.operating-hours.save');
    });

    // Voucher & Promotion Viewing (all authorized staff can view active list & validate)
    Route::middleware(['can:view-vouchers', 'schema:settings'])->group(function () {
        Route::get('/admin/api/vouchers', [VoucherController::class, 'index'])->name('admin.vouchers.api.index');
        Route::post('/admin/api/vouchers/validate', [VoucherController::class, 'validateCode'])->name('admin.vouchers.validate');
    });

    // Voucher & Promotion Management (Owner-only CRUD)
    Route::middleware(['can:manage-vouchers', 'schema:settings'])->group(function () {
        Route::post('/admin/api/vouchers', [VoucherController::class, 'store'])->name('admin.vouchers.store');
        Route::put('/admin/api/vouchers/{voucher}', [VoucherController::class, 'update'])->whereNumber('voucher')->name('admin.vouchers.update');
        Route::patch('/admin/api/vouchers/{voucher}/toggle', [VoucherController::class, 'toggle'])->whereNumber('voucher')->name('admin.vouchers.toggle');
        Route::delete('/admin/api/vouchers/{voucher}', [VoucherController::class, 'destroy'])->whereNumber('voucher')->name('admin.vouchers.destroy');
    });

    // Crew Management (Owner/Manager)
    Route::middleware(['can:manage-crew', 'schema:shifts'])->group(function () {
        Route::post('/admin/shifts', [ShiftController::class, 'store'])->name('admin.shifts.store');
        Route::get('/admin/api/shifts', [ShiftController::class, 'schedule'])->name('admin.shifts.data');
        Route::post('/admin/api/shifts', [ShiftController::class, 'store']);
        Route::delete('/admin/api/shifts/{shift}', [ShiftController::class, 'destroy'])->name('admin.shifts.destroy');
        Route::get('/admin/api/shift-templates', [ShiftController::class, 'getTemplates'])->name('admin.shifts.templates');
        Route::post('/admin/api/shift-templates', [ShiftController::class, 'saveTemplates'])->name('admin.shifts.templates.save');
        Route::post('/admin/api/shifts/replicate-months', [ShiftController::class, 'replicateMonths'])->name('admin.shifts.replicate');
    });

    // My Schedule (Crew/Barista — personal view only)
    Route::middleware(['can:view-own-schedule', 'schema:shifts'])->group(function () {
        Route::get('/admin/my-schedule', [ShiftController::class, 'mySchedule'])->name('admin.my-schedule');
        Route::get('/admin/crew-home', fn () => redirect()->route('admin.my-schedule'))->name('admin.crew.home');
    });

    // Kitchen Display (Barista/Kitchen)
    Route::middleware(['can:view-kitchen', 'schema:orders'])->group(function () {
        Route::get('/admin/kitchen', [KitchenController::class, 'index'])->name('admin.kitchen.index');
        Route::get('/admin/kitchen/orders', fn () => response()->json(
            Order::where('shop_id', auth()->user()->shop_id)
                ->whereIn('order_status', ['CONFIRMED', 'PREPARING', 'READY'])
                ->with(['items', 'table'])
                ->latest()
                ->limit(150)
                ->get()
                ->map(fn ($o) => [
                    'id' => $o->id,
                    'table' => $o->table?->name ?? ($o->fulfillment_type === 'TAKEAWAY' ? 'Takeaway' : ($o->fulfillment_type === 'DELIVERY' ? 'Delivery' : 'Tanpa Meja')),
                    'customer' => $o->customer_name ?: 'Walk-in Customer',
                    'type' => $o->fulfillment_type === 'DINE_IN' ? 'Dine In' : ($o->fulfillment_type === 'TAKEAWAY' ? 'Takeaway' : 'Delivery'),
                    'fulfillment_type' => $o->fulfillment_type ?? 'DINE_IN',
                    'status' => $o->order_status,
                    'time' => $o->created_at->format('H:i'),
                    'items' => $o->items->map(fn ($i) => [
                        'id' => $i->id, 'name' => $i->product_name,
                        'qty' => $i->quantity, 'variant' => $i->variant_name,
                        'modifiers' => $i->modifiers ?? [],
                        'notes' => $i->notes,
                    ]),
                ])
        ))->name('admin.kitchen.orders');
        Route::post('/admin/kitchen/orders/{order}/status', [KitchenController::class, 'updateStatus'])->middleware('schema:location_inventory')->name('admin.kitchen.orders.status');
    });

    // Reporting & Analytics
    Route::middleware(['can:view-reports', 'schema:reports'])->group(function () {
        Route::get('/admin/reports', [ReportController::class, 'index'])->name('admin.reports.index');
    });

    Route::get('/admin/api/logs', [DashboardController::class, 'getLogs'])->middleware(['can:view-reports', 'schema:audit'])->name('admin.logs.index');

    // Profile (all authenticated users)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
};

Route::middleware(['auth', ResolveBranch::class])->group($operatingRoutes);
Route::middleware(['auth', ResolveBranch::class])->prefix('cabang/{operatingBranch}')
    ->where(['operatingBranch' => '[a-z0-9-]+'])->name('branch.')->group($operatingRoutes);

foreach (Route::getRoutes() as $route) {
    if ($route->getName() === 'branch.') {
        $action = $route->getAction();
        unset($action['as']);
        $route->setAction($action);
    }
}

require __DIR__.'/auth.php';

// SuperAdmin Panel
Route::middleware(['auth', 'superadmin', 'schema:platform'])->prefix('superadmin')->name('superadmin.')->group(function () {
    Route::get('/dashboard', [SuperAdminController::class, 'index'])->name('dashboard');
    Route::resource('shops', App\Http\Controllers\SuperAdmin\ShopController::class);
    Route::post('/shops/{shop}/suspend', [App\Http\Controllers\SuperAdmin\ShopController::class, 'suspend'])->name('shops.suspend');
    Route::post('/shops/{shop}/activate', [App\Http\Controllers\SuperAdmin\ShopController::class, 'activate'])->name('shops.activate');
    Route::resource('users', UserController::class);
});

// Customer Receipt (Signed URLs)
Route::get('/receipt/{order}', [ReceiptController::class, 'show'])->middleware('schema:orders')->name('receipt.web');
Route::get('/receipt/{order}/pdf', [ReceiptController::class, 'downloadPdf'])->middleware('schema:orders')->name('receipt.pdf');

// Custom Domain Storefront (resolved by ResolveCustomDomain middleware)
Route::get('/cart', [ShopController::class, 'cartCustomDomain'])->name('custom-domain.cart');
Route::get('/tracking', [ShopController::class, 'trackingCustomDomain'])->name('custom-domain.tracking');
Route::post('/voucher/validate', [ShopController::class, 'validateVoucherCustomDomain'])->middleware('schema:checkout')->name('custom-domain.voucher.validate');
Route::post('/order', [ShopController::class, 'submitOrderCustomDomain'])->middleware('schema:checkout')->name('custom-domain.order.submit');

// Public Customer Menu
Route::get('/{slug}', [ShopController::class, 'show'])->middleware('schema:catalog')->name('shop.menu');
Route::get('/{slug}/cart', [ShopController::class, 'cart'])->name('shop.cart');
Route::get('/{slug}/tracking', [ShopController::class, 'tracking'])->name('shop.tracking');
Route::post('/{slug}/voucher/validate', [ShopController::class, 'validateVoucher'])->middleware('schema:checkout')->name('shop.voucher.validate');
Route::post('/{slug}/order', [ShopController::class, 'submitOrder'])->middleware('schema:checkout')->name('shop.order.submit');

// Branch-scoped Public Customer Menu
Route::get('/{slug}/{branchSlug}', [ShopController::class, 'showBranch'])
    ->where('branchSlug', '(?!cart$|tracking$)[^/]+')
    ->middleware('schema:catalog')
    ->name('shop.branch.menu');
Route::get('/{slug}/{branchSlug}/cart', [ShopController::class, 'cartBranch'])->name('shop.branch.cart');
Route::get('/{slug}/{branchSlug}/tracking', [ShopController::class, 'trackingBranch'])->name('shop.branch.tracking');
Route::post('/{slug}/{branchSlug}/voucher/validate', [ShopController::class, 'validateVoucherBranch'])->middleware('schema:checkout')->name('shop.branch.voucher.validate');
Route::post('/{slug}/{branchSlug}/order', [ShopController::class, 'submitOrderBranch'])->middleware('schema:checkout')->name('shop.branch.order.submit');
