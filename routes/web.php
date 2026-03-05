<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;

Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

Route::get('/', function () {
    return redirect('/admin/index');
});


Route::get('lang/{locale}', function ($locale) {
    if (in_array($locale, ['ar', 'en'])) {
        session()->put('locale', $locale);
    }
    return redirect()->back();
})->name('lang.switch');

Route::prefix('admin')->name('admin.')->middleware('auth')->group(function () {
    Route::get('/settings', [\App\Http\Controllers\Admin\AppSettingController::class, 'index'])->name('settings.index');
    Route::post('/settings', [\App\Http\Controllers\Admin\AppSettingController::class, 'update'])->name('settings.update');
    Route::get('/users', function () {
        return view('admin.users');
    })->name('users.index');
    Route::get('/customers', function () {
        return view('admin.customers');
    })->name('customers.index');
    Route::get('/customers/{id}/ledger', function ($id) {
        return view('admin.customer-ledger', ['id' => $id]);
    })->name('customers.ledger');
    Route::get('/categories', function () {
        return view('admin.categories');
    })->name('categories.index');
    Route::get('/products', function () {
        return view('admin.products');
    })->name('products.index');
    Route::get('/products/{id}/history', function ($id) {
        return view('admin.stock-history', ['id' => $id]);
    })->name('products.history');
    Route::get('/suppliers', function () {
        return view('admin.suppliers');
    })->name('suppliers.index');
    Route::get('/suppliers/{id}/ledger', function ($id) {
        return view('admin.supplier-ledger', ['id' => $id]);
    })->name('suppliers.ledger');
    Route::get('/purchases', function () {
        return view('admin.purchases');
    })->name('purchases.index');
    Route::get('/sales', function () {
        return view('admin.sales');
    })->name('sales.index');
    Route::get('/employees', function () {
        return view('admin.employees');
    })->name('employees.index');
    Route::get('/employees/{id}/ledger', function ($id) {
        return view('admin.employee-ledger', ['id' => $id]);
    })->name('employees.ledger');
    Route::get('/warehouses', function () {
        return view('admin.warehouses');
    })->name('warehouses.index');
    Route::get('/warehouses/{id}', function ($id) {
        return view('admin.warehouse-show', ['id' => $id]);
    })->name('warehouses.show');
    Route::get('/stock-transfer', function () {
        return view('admin.stock-transfer');
    })->name('stock-transfer.index');

    // Phase 8: Finance
    Route::get('/vouchers', function () {
        return view('admin.vouchers');
    })->name('vouchers.index');
    Route::get('/settings/exchange-rate', function () {
        return view('admin.exchange-rate');
    })->name('settings.exchange-rate');

    // Phase 10: Reports
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/sales', function () {
            return view('admin.reports-sales');
        })->name('sales');
        Route::get('/purchases', function () {
            return view('admin.reports-purchases');
        })->name('purchases');
        Route::get('/profit', function () {
            return view('admin.reports-profit');
        })->name('profit');
        Route::get('/stock', function () {
            return view('admin.reports-stock');
        })->name('stock');
    });

    // Phase 10: Printable Views
    Route::get('/sales/{id}/print', function ($id) {
        return view('admin.invoice-print', ['id' => $id, 'type' => 'sale']);
    })->name('sales.print');
    Route::get('/purchases/{id}/print', function ($id) {
        return view('admin.invoice-print', ['id' => $id, 'type' => 'purchase']);
    })->name('purchases.print');
    Route::get('/vouchers/{id}/print', function ($id) {
        return view('admin.voucher-print', ['id' => $id]);
    })->name('vouchers.print');

    Route::get('/{page}', [DashboardController::class, 'index'])->where('page', '[A-Za-z0-9\-]+')->name('dashboard');
});