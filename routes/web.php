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
    Route::get('/sales/invoices', function () {
        return view('admin.sales', ['type' => 'invoice']);
    })->name('sales.index'); // Keep index for compatibility
    Route::get('/sales/quotations', function () {
        return view('admin.sales', ['type' => 'quotation']);
    })->name('sales.quotations');
    Route::get('/sales/proforma', function () {
        return view('admin.sales', ['type' => 'proforma']);
    })->name('sales.proforma');
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
    Route::get('/debts', function () {
        return view('admin.debts');
    })->name('debts.index');
    Route::get('/settings/exchange-rate', function () {
        return view('admin.exchange-rate');
    })->name('settings.exchange-rate');

    // Phase 11: Treasury (Cashbox)
    Route::get('/accounts', function () {
        return view('admin.accounts');
    })->name('accounts.index');
    Route::get('/accounts/{id}/ledger', function ($id) {
        return view('admin.account-ledger', ['id' => $id]);
    })->name('accounts.ledger');
    
    Route::get('/accounts/{id}/ledger/print', function (Illuminate\Http\Request $request, $id) {
        $fromDate = $request->query('fromDate', now()->startOfMonth()->format('Y-m-d'));
        $toDate = $request->query('toDate', now()->format('Y-m-d'));
        
        $account = \App\Models\FinancialAccount::findOrFail($id);
        $entries = \App\Models\AccountLedger::where('account_id', $id)
            ->whereBetween('date', [$fromDate, $toDate])
            ->orderBy('date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $previousBalance = \App\Models\AccountLedger::where('account_id', $id)
            ->where('date', '<', $fromDate)
            ->selectRaw('SUM(debit) - SUM(credit) as balance')
            ->first()->balance ?? 0;

        return view('admin.account-ledger-print', compact('account', 'entries', 'previousBalance', 'fromDate', 'toDate'));
    })->name('accounts.ledger.print');

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
    Route::get('/customers/{id}/report/print', function ($id) {
        return view('admin.customer-report-print', ['id' => $id]);
    })->name('customers.report.print');

    Route::get('/sales/{id}/print', function ($id) {
        return view('admin.invoice-print', ['id' => $id, 'type' => 'sale']);
    })->name('sales.print');
    Route::get('/purchases/{id}/print', function ($id) {
        return view('admin.invoice-print', ['id' => $id, 'type' => 'purchase']);
    })->name('purchases.print');
    Route::get('/vouchers/{id}/print', function ($id) {
        return view('admin.voucher-print', ['id' => $id]);
    })->name('vouchers.print');

    Route::get('/activity-log', function () {
        return view('admin.activity-log');
    })->name('activity-log.index');

    Route::get('/{page}', [DashboardController::class, 'index'])->where('page', '[A-Za-z0-9\-]+')->name('dashboard');
});