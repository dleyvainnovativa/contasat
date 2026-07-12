<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ContabilidadController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\PeriodController;
use App\Http\Controllers\ReconciliationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SatController;
use App\Http\Controllers\StatementController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web routes
|--------------------------------------------------------------------------
| Phase 0: authentication, the multi-client dashboard, client CRUD, and
| period selection. The 'firebase' middleware alias maps to
| App\Http\Middleware\VerifyFirebaseToken (registered in bootstrap/app.php).
*/

// Public auth screens
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/auth/session', [AuthController::class, 'session'])->name('auth.session');
Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

Route::middleware('firebase')->group(function () {

    Route::get('/', fn () => redirect()->route('dashboard'));
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Clients
    Route::resource('clients', ClientController::class);
    Route::post('/clients/{client}/activate', [ClientController::class, 'activate'])->name('clients.activate');

    // Periods
    Route::post('/clients/{client}/periods/open', [PeriodController::class, 'open'])->name('periods.open');
    Route::post('/periods/{period}/activate', [PeriodController::class, 'activate'])->name('periods.activate');

    // Invoices (Phase 1) — operate on the active client + period
    Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::post('/invoices/upload', [InvoiceController::class, 'upload'])->name('invoices.upload');
    Route::get('/invoices/uploads/{upload}/status', [InvoiceController::class, 'status'])->name('invoices.status');
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::get('/invoices/{invoice}/xml', [InvoiceController::class, 'xml'])->name('invoices.xml');

    // Bank statements (Phase 2) — AI extraction + balance gate
    Route::get('/statements', [StatementController::class, 'index'])->name('statements.index');
    Route::post('/statements/upload', [StatementController::class, 'upload'])->name('statements.upload');
    Route::get('/statements/{statement}/status', [StatementController::class, 'status'])->name('statements.status');
    Route::get('/statements/{statement}', [StatementController::class, 'show'])->name('statements.show');
    Route::post('/statements/{statement}/reextract', [StatementController::class, 'reextract'])->name('statements.reextract');
    Route::delete('/statements/{statement}', [StatementController::class, 'destroy'])->name('statements.destroy');

    // Reconciliation (Phase 3) — the matching engine + review UI
    Route::get('/reconciliation', [ReconciliationController::class, 'index'])->name('reconciliation.index');
    Route::post('/reconciliation/run', [ReconciliationController::class, 'run'])->name('reconciliation.run');
    Route::post('/reconciliation/matches/{match}/confirm', [ReconciliationController::class, 'confirm'])->name('reconciliation.confirm');
    Route::post('/reconciliation/matches/{match}/reject', [ReconciliationController::class, 'reject'])->name('reconciliation.reject');
    Route::post('/reconciliation/link', [ReconciliationController::class, 'link'])->name('reconciliation.link');
    Route::post('/reconciliation/movements/{movement}/account', [ReconciliationController::class, 'assignAccount'])->name('reconciliation.account');
    Route::get('/reconciliation/questions', [ReconciliationController::class, 'questions'])->name('reconciliation.questions');

    // Reports (Phase 4)
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/polizas', [ReportController::class, 'polizas'])->name('reports.polizas');
    Route::get('/reports/income-expense', [ReportController::class, 'incomeExpense'])->name('reports.income_expense');
    Route::get('/reports/polizas/export', [ReportController::class, 'exportPolizas'])->name('reports.polizas.export');
    Route::get('/reports/income-expense/export', [ReportController::class, 'exportIncomeExpense'])->name('reports.income_expense.export');

    // Contabilidad electrónica (Phase 5) — SAT XML generation
    Route::get('/contabilidad', [ContabilidadController::class, 'index'])->name('contabilidad.index');
    Route::get('/contabilidad/preview/{type}', [ContabilidadController::class, 'preview'])->name('contabilidad.preview');
    Route::get('/contabilidad/download/{type}', [ContabilidadController::class, 'download'])->name('contabilidad.download');
    Route::get('/contabilidad/zip', [ContabilidadController::class, 'downloadZip'])->name('contabilidad.zip');

    // SAT Descarga Masiva (Phase 6) — e.firma credentials + automated CFDI download
    Route::get('/sat', [SatController::class, 'index'])->name('sat.index');
    Route::post('/sat/clients/{client}/credential', [SatController::class, 'storeCredential'])->name('sat.credential.store');
    Route::delete('/sat/clients/{client}/credential', [SatController::class, 'destroyCredential'])->name('sat.credential.destroy');
    Route::post('/sat/clients/{client}/download', [SatController::class, 'requestDownload'])->name('sat.download.request');
    Route::get('/sat/requests/{satRequest}/status', [SatController::class, 'status'])->name('sat.request.status');
});
