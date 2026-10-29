<?php

use App\Http\Controllers\BudgetController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\TransferController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::inertia('/', 'welcome')->name('home');

Route::get('dashboard', function (Request $request) {
    $user = $request->user();
    $household = $user->currentHousehold ?? $user->households()->first();

    return $household ? redirect()->route('households.home', $household) : Inertia::render('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified', 'household.member'])
    ->prefix('h/{household}')
    ->scopeBindings() // {budget} and {transaction} only resolve inside this household
    ->group(function () {
        Route::get('/', HomeController::class)->name('households.home');

        Route::controller(BudgetController::class)
            ->prefix('budgets')
            ->name('budgets.')
            ->group(function () {
                Route::get('/', 'edit')->name('edit');
                Route::put('/', 'update')->name('update');
                Route::get('/{budget}', 'show')->name('show');
            });

        Route::resource('transactions', TransactionController::class);

        Route::controller(TransferController::class)
            ->prefix('transfers')
            ->name('transfers.')
            ->group(function () {
                Route::get('/create', 'create')->name('create');
                Route::post('/', 'store')->name('store');
                Route::delete('/{transfer}', 'destroy')->whereUuid('transfer')->name('destroy');
            });
    });

require __DIR__.'/settings.php';
