<?php

use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\FinanceCategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\UnitController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FinanceReportController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\SupplierProfileController;
use App\Http\Controllers\VendorInvoiceController;
use App\Models\InventoryStock;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('media/{path}', function (string $path) {
    $path = ltrim($path, '/');

    if (str_contains($path, '..') || ! Storage::disk('public')->exists($path)) {
        abort(404);
    }

    return response()->file(Storage::disk('public')->path($path));
})->where('path', '.*')->name('media.public');

Route::middleware(['auth', 'verified'])->group(function () {
    // =========================================================================
    // Dashboard & Profile
    // =========================================================================
    Route::get('/', function () {
        return redirect()->route('dashboard');
    });
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::view('profile', 'profile.index')->name('profile.index');
    Route::view('companies', 'companies.index')->middleware('role:admin,manager')->name('companies.index');
    Route::view('inventory', 'inventory.index')->middleware('role:admin,manager')->name('inventory.index');
    Route::get('inventory/{inventoryStock}', function (InventoryStock $inventoryStock) {
        $inventoryStock->load([
            'product.category',
            'product.unit',
            'product.company',
            'product.purchaseItems.purchase.supplier',
            'product.inventoryMovements.purchase.supplier',
            'product.inventoryMovements.purchaseItem',
            'product.inventoryMovements.sale',
            'product.inventoryMovements.saleItem',
        ]);

        return view('inventory.show', compact('inventoryStock'));
    })->middleware('role:admin,manager')->name('inventory.show');
    Route::patch('inventory/{inventoryStock}/batches/{purchaseItem}', function (
        Request $request,
        InventoryStock $inventoryStock,
        PurchaseItem $purchaseItem
    ) {
        abort_unless((int) $purchaseItem->product_id === (int) $inventoryStock->product_id, 404);

        $validated = $request->validate([
            'manufacturing_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:manufacturing_date'],
        ]);

        $purchaseItem->update($validated);

        return back()->with('success', 'Batch dates updated successfully.');
    })->middleware('role:admin,manager')->name('inventory.batches.update');
    Route::get('companies/{company}', [CompanyController::class, 'show'])->middleware('role:admin,manager')->name('companies.show');
    Route::delete('companies/{company}', [CompanyController::class, 'destroy'])->middleware('role:admin,manager')->name('companies.destroy');
    Route::get('suppliers/{supplier}/profile.pdf', [SupplierProfileController::class, 'download'])->middleware('role:admin,manager')->name('suppliers.profile.pdf');

    // =========================================================================
    // Master Data
    // =========================================================================
    Route::view('master/customers', 'customers.index')->middleware('role:admin,sales')->name('customers.index');
    Route::prefix('master')->middleware('role:admin,manager')->group(function () {
        Route::view('suppliers/create', 'suppliers.create')->name('suppliers.create');
        Route::get('suppliers/{supplier}', function (Supplier $supplier) {
            $supplier->load('companies');

            return view('suppliers.show', compact('supplier'));
        })->name('suppliers.show');
        Route::get('suppliers/{supplier}/edit', function (Supplier $supplier) {
            return view('suppliers.edit', compact('supplier'));
        })->name('suppliers.edit');
        Route::view('suppliers', 'suppliers.index')->name('suppliers.index');
        Route::view('companies', 'companies.index')->name('master.companies.index');
        Route::view('categories', 'categories.index')->name('categories.index');
        Route::view('units', 'units.index')->name('units.index');
        Route::view('products', 'products.index')->name('products.index');
    });

    // =========================================================================
    // Transactions
    // =========================================================================

    // Purchases
    Route::resource('purchases', PurchaseController::class)->middleware('role:admin,manager');
    Route::prefix('purchases/{purchase}')->name('purchases.')->controller(PurchaseController::class)->middleware('role:admin,manager')->group(function () {
        Route::get('print', 'print')->name('print');
        Route::get('receive', 'receive')->name('receive');
        Route::patch('ordered', 'markOrdered')->name('mark-ordered');
        Route::patch('received', 'markReceived')->name('mark-received');
        Route::patch('paid', 'markPaid')->name('mark-paid');
        Route::patch('cancel', 'cancel')->name('cancel');
        Route::patch('restore-draft', 'restoreToDraft')->name('restore-draft');
    });

    // Sales
    Route::resource('sales', SalesController::class)->except(['edit', 'update'])->middleware('role:admin,sales');
    Route::prefix('sales/{sale}')->name('sales.')->controller(SalesController::class)->middleware('role:admin,sales')->group(function () {
        Route::get('print', 'print')->name('print');
        Route::patch('complete', 'complete')->name('complete');
        Route::patch('restore', 'restore')->name('restore');
    });

    // =========================================================================
    // Admin
    // =========================================================================
    Route::view('coupons', 'coupons.index')->middleware('role:admin')->name('coupons.index');

    // =========================================================================
    // Finance
    // =========================================================================
    Route::prefix('finance')->name('finance.')->middleware('role:admin')->group(function () {
        Route::view('categories', 'finance-categories.index')->name('categories.index');
        Route::view('transactions', 'finance-transactions.index')->name('transactions.index');
        Route::get('transactions/print/{printId}', [FinanceReportController::class, 'print'])->name('transactions.print');
    });

    Route::get('vendor-invoices', [VendorInvoiceController::class, 'index'])->middleware('role:admin,manager')->name('vendor-invoices.index');
    Route::get('vendor-invoices/{vendorInvoice}', [VendorInvoiceController::class, 'show'])->middleware('role:admin,manager')->name('vendor-invoices.show');
    Route::patch('vendor-invoices/{vendorInvoice}/paid', [VendorInvoiceController::class, 'markPaid'])->middleware('role:admin,manager')->name('vendor-invoices.mark-paid');

    // =========================================================================
    // Settings & Users
    // =========================================================================
    Route::view('users', 'users.index')->middleware('role:admin')->name('users.index');
    Route::view('settings', 'settings.index')->middleware('role:admin')->name('settings.index');

    // =========================================================================
    // Internal APIs (AJAX)
    // =========================================================================
    Route::prefix('ajax')->name('ajax.')->group(function () {
        Route::post('products', [ProductController::class, 'search'])->middleware('role:admin,manager,sales')->name('products.search');
        Route::post('purchases/preview-reference', [PurchaseController::class, 'previewReference'])->middleware('role:admin,manager')->name('purchases.preview-reference');
        Route::post('suppliers', [SupplierController::class, 'search'])->middleware('role:admin,manager')->name('suppliers.search');
        Route::post('companies', [App\Http\Controllers\Api\CompanyController::class, 'search'])->middleware('role:admin,manager')->name('companies.search');
        Route::post('customers', [CustomerController::class, 'search'])->middleware('role:admin,sales')->name('customers.search');
        Route::post('customers/store', [CustomerController::class, 'store'])->middleware('role:admin,sales')->name('customers.store');
        Route::post('categories', [CategoryController::class, 'search'])->middleware('role:admin,manager')->name('categories.search');
        Route::post('units', [UnitController::class, 'search'])->middleware('role:admin,manager')->name('units.search');
        Route::post('users', [UserController::class, 'search'])->middleware('role:admin,manager')->name('users.search');
        Route::post('finance-categories', [FinanceCategoryController::class, 'search'])->middleware('role:admin')->name('finance-categories.search');
    });
});

require __DIR__.'/auth.php';
