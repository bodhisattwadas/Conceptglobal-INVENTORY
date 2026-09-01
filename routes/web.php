<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FinanceReportController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\VendorInvoiceController;
use App\Http\Controllers\SupplierProfileController;
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
    Route::view('companies', 'companies.index')->name('companies.index');
    Route::view('inventory', 'inventory.index')->name('inventory.index');
    Route::get('inventory/{inventoryStock}', function (\App\Models\InventoryStock $inventoryStock) {
        $inventoryStock->load([
            'product.category',
            'product.unit',
            'product.company',
            'product.purchaseItems.purchase.supplier',
        ]);

        return view('inventory.show', compact('inventoryStock'));
    })->name('inventory.show');
    Route::patch('inventory/{inventoryStock}/batches/{purchaseItem}', function (
        \Illuminate\Http\Request $request,
        \App\Models\InventoryStock $inventoryStock,
        \App\Models\PurchaseItem $purchaseItem
    ) {
        abort_unless((int) $purchaseItem->product_id === (int) $inventoryStock->product_id, 404);

        $validated = $request->validate([
            'manufacturing_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:manufacturing_date'],
        ]);

        $purchaseItem->update($validated);

        return back()->with('success', 'Batch dates updated successfully.');
    })->name('inventory.batches.update');
    Route::get('companies/{company}', [CompanyController::class, 'show'])->name('companies.show');
    Route::delete('companies/{company}', [CompanyController::class, 'destroy'])->name('companies.destroy');
    Route::get('suppliers/{supplier}/profile.pdf', [SupplierProfileController::class, 'download'])->name('suppliers.profile.pdf');

    // =========================================================================
    // Master Data
    // =========================================================================
    Route::prefix('master')->group(function () {
        Route::view('customers', 'customers.index')->name('customers.index');
        Route::view('suppliers/create', 'suppliers.create')->name('suppliers.create');
        Route::get('suppliers/{supplier}', function (\App\Models\Supplier $supplier) {
            $supplier->load('companies');

            return view('suppliers.show', compact('supplier'));
        })->name('suppliers.show');
        Route::get('suppliers/{supplier}/edit', function (\App\Models\Supplier $supplier) {
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
    Route::resource('purchases', PurchaseController::class);
    Route::prefix('purchases/{purchase}')->name('purchases.')->controller(PurchaseController::class)->group(function () {
        Route::get('print', 'print')->name('print');
        Route::get('receive', 'receive')->name('receive');
        Route::patch('ordered', 'markOrdered')->name('mark-ordered');
        Route::patch('received', 'markReceived')->name('mark-received');
        Route::patch('paid', 'markPaid')->name('mark-paid');
        Route::patch('cancel', 'cancel')->name('cancel');
        Route::patch('restore-draft', 'restoreToDraft')->name('restore-draft');
    });

    // Sales
    Route::resource('sales', SalesController::class)->except(['edit', 'update']);
    Route::prefix('sales/{sale}')->name('sales.')->controller(SalesController::class)->group(function () {
        Route::get('print', 'print')->name('print');
        Route::patch('complete', 'complete')->name('complete');
        Route::patch('restore', 'restore')->name('restore');
    });

    // =========================================================================
    // Admin
    // =========================================================================
    Route::view('coupons', 'coupons.index')->name('coupons.index');

    // =========================================================================
    // Finance
    // =========================================================================
    Route::prefix('finance')->name('finance.')->group(function () {
        Route::view('categories', 'finance-categories.index')->name('categories.index');
        Route::view('transactions', 'finance-transactions.index')->name('transactions.index');
        Route::get('transactions/print/{printId}', [FinanceReportController::class, 'print'])->name('transactions.print');
    });

    Route::get('vendor-invoices', [VendorInvoiceController::class, 'index'])->name('vendor-invoices.index');
    Route::get('vendor-invoices/{vendorInvoice}', [VendorInvoiceController::class, 'show'])->name('vendor-invoices.show');
    Route::patch('vendor-invoices/{vendorInvoice}/paid', [VendorInvoiceController::class, 'markPaid'])->name('vendor-invoices.mark-paid');

    // =========================================================================
    // Settings & Users
    // =========================================================================
    Route::view('users', 'users.index')->name('users.index');
    Route::view('settings', 'settings.index')->name('settings.index');

    // =========================================================================
    // Internal APIs (AJAX)
    // =========================================================================
    Route::prefix('ajax')->name('ajax.')->group(function () {
        Route::post('products', [\App\Http\Controllers\Api\ProductController::class, 'search'])->name('products.search');
        Route::post('purchases/preview-reference', [PurchaseController::class, 'previewReference'])->name('purchases.preview-reference');
        Route::post('suppliers', [\App\Http\Controllers\Api\SupplierController::class, 'search'])->name('suppliers.search');
        Route::post('companies', [\App\Http\Controllers\Api\CompanyController::class, 'search'])->name('companies.search');
        Route::post('customers', [\App\Http\Controllers\Api\CustomerController::class, 'search'])->name('customers.search');
        Route::post('customers/store', [\App\Http\Controllers\Api\CustomerController::class, 'store'])->name('customers.store');
        Route::post('categories', [\App\Http\Controllers\Api\CategoryController::class, 'search'])->name('categories.search');
        Route::post('units', [\App\Http\Controllers\Api\UnitController::class, 'search'])->name('units.search');
        Route::post('users', [\App\Http\Controllers\Api\UserController::class, 'search'])->name('users.search');
        Route::post('finance-categories', [\App\Http\Controllers\Api\FinanceCategoryController::class, 'search'])->name('finance-categories.search');
    });
});

require __DIR__.'/auth.php';
