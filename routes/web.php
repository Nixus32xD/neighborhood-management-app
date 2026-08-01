<?php

use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\ExpensesController;
use App\Http\Controllers\Dashboard\LotsController;
use App\Http\Controllers\Dashboard\OwnerController;
use App\Http\Controllers\Dashboard\OwnerStatementController;
use App\Http\Controllers\Dashboard\PaymentsController;
use App\Http\Controllers\Dashboard\PaymentsMethodsController;
use App\Http\Controllers\ProfileController;
use App\Models\Neighborhood;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Auth/Login');
});

Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');
Route::middleware(['auth'])->group(function () {
    Route::resource('owners', OwnerController::class);
});

Route::middleware(['auth'])->group(function () {
    Route::resource('expenses', ExpensesController::class);
    Route::post('/expenses/accumulated', [ExpensesController::class, 'storeAccumulated'])
        ->name('expenses.accumulated.store');
    Route::post('/expenses/generate', [ExpensesController::class, 'generate'])
        ->name('expenses.generate');
    Route::post('/expenses/{expense}/fine', [ExpensesController::class, 'addFine'])
        ->name('expenses.fine');

    Route::post('/expenses/extraordinary', [ExpensesController::class, 'addExtraordinary'])
        ->name('expenses.extraordinary');
});

Route::middleware(['auth'])->group(function () {
    Route::resource('payments', PaymentsController::class);
});

Route::get('/payments/{payment}/voucher', [PaymentsController::class, 'voucher'])
    ->middleware('auth')
    ->name('payments.voucher');

Route::get('/reports/payments/reconciliation/monthly', [PaymentsController::class, 'reconciliation'])
    ->middleware('auth')
    ->name('payments.reconciliation.monthly');

Route::put('/payments/bank-accounts/{bankAccount}/opening-balance', [PaymentsController::class, 'updateOpeningBalance'])
    ->middleware('auth')
    ->name('payments.bank-accounts.opening-balance');


Route::middleware(['auth'])->group(function () {
    Route::resource('payment-methods', PaymentsMethodsController::class);
});
Route::middleware(['auth'])->group(function () {
    Route::resource('lots', LotsController::class);
});

Route::middleware(['auth'])->group(function () {
    Route::get('/owner-statements', [OwnerStatementController::class, 'index'])
        ->name('owner-statements.index');
    Route::get('/owner-statements/print', [OwnerStatementController::class, 'print'])
        ->name('owner-statements.print');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/select-neighborhood', function () {
        return Inertia::render('Auth/SelectNeighborhood', [
            'neighborhoods' => Neighborhood::select('id', 'name')->get()
        ]);
    })->name('neighborhood.select');

    Route::post('/select-neighborhood', function (Request $request) {
        $request->validate([
            'neighborhood' => ['required', 'exists:neighborhoods,id'],
        ]);

        session(['neighborhood_id' => $request->neighborhood]);

        return redirect()->route('dashboard');
    });
});


Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';
