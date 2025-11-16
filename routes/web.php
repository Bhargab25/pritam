<?php

use App\Livewire\ClientManagement;
use App\Livewire\InventoryManagent;
use App\Livewire\ProductCategory;
use App\Livewire\Product;
use App\Livewire\SupplierManagement;
use App\Livewire\InvoiceManagement;
use App\Livewire\SalesReports;
use App\Livewire\SalesAnalytics;
use App\Livewire\ExpenseManagement;
use App\Livewire\SystemSettings;
use App\Livewire\BackupExport;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use App\Models\Invoice;
use App\Services\InvoicePdfService;
use App\Models\MonthlyBill;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Livewire\UserProfile;
use App\Livewire\CashFlow;

Route::view('/', 'welcome');

Route::post('/logout', function (Request $request) {
    // Log the logout activity if user exists
    if (Auth::check()) {
        $user = Auth::user();
        
        // Log logout activity (if you have the UserActivityLog model)
        if (method_exists($user, 'logActivity')) {
            $user->logActivity('logout');
        }
    }
    
    Auth::logout();
    
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    
    return redirect('/login');
})->middleware('auth')->name('logout');

Route::middleware(['auth'])->group(function () {
    Route::get('/profile', UserProfile::class)
        ->name('profile.edit');
});

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::get('/categories', ProductCategory::class)
    ->middleware(['auth', 'verified'])
    ->name('categories.index');

Route::get('/products', Product::class)
    ->middleware(['auth', 'verified'])
    ->name('products.index');

Route::get('/suppliers', SupplierManagement::class)
    ->middleware(['auth', 'verified'])
    ->name('suppliers.index');

Route::get('/clients', ClientManagement::class)
    ->middleware(['auth', 'verified'])
    ->name('clients.index');

Route::get('/inventory', InventoryManagent::class)
    ->middleware(['auth', 'verified'])
    ->name('inventory.index');

Route::get('/invoices', InvoiceManagement::class)
    ->middleware(['auth', 'verified'])
    ->name('invoices.index');

Route::get('/sales-reports', SalesReports::class)
    ->middleware(['auth', 'verified'])
    ->name('sales-reports.index');

Route::get('/cash-flow', CashFlow::class)
    ->middleware(['auth', 'verified'])
    ->name('cash-flow.index');

Route::get('/sales-analytics', SalesAnalytics::class)
    ->middleware(['auth', 'verified'])
    ->name('sales-analytics.index');

Route::get('/expenses', ExpenseManagement::class)
    ->middleware(['auth', 'verified'])
    ->name('expenses.index');

Route::get('/settings/backup', BackupExport::class)
    ->middleware(['auth', 'verified'])
    ->name('backup-export.index');

Route::get('/settings/system', SystemSettings::class)
    ->middleware(['auth', 'verified'])
    ->name('system-settings.index');

Route::get('/settings/company', \App\Livewire\CompanyProfile::class)
    ->middleware(['auth', 'verified'])
    ->name('company-profile.index');

Route::get('/settings/users', \App\Livewire\UserManagement::class)
    ->middleware(['auth', 'verified'])
    ->name('user-management.index');

Route::middleware(['auth'])->group(function () {
    Route::get('/monthly-bill/download/{monthlyBill}', function (MonthlyBill $monthlyBill) {
        $pdfService = new \App\Services\InvoicePdfService();
        $pdfPath = $pdfService->generateMonthlyBillPdf($monthlyBill);
        return response()->download($pdfPath)->deleteFileAfterSend();
    })->name('monthly-bill.download');

    Route::get('/invoice/download/{invoice}', function (Invoice $invoice) {
        $pdfService = new \App\Services\InvoicePdfService();
        $pdfPath = $pdfService->generateInvoicePdf($invoice);
        return response()->download($pdfPath)->deleteFileAfterSend();
    })->name('invoice.download');
});

Route::get('/download/ledger-pdf/{path}', function ($path) {
    $decodedPath = base64_decode($path);

    if (!Storage::exists($decodedPath)) {
        abort(404, 'File not found');
    }

    return Storage::download($decodedPath);
})->name('download.ledger.pdf')->middleware('auth');


require __DIR__ . '/auth.php';
