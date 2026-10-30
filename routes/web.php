<?php

use App\Http\Controllers\BudgetController;
use App\Http\Controllers\CloseMonthController;
use App\Http\Controllers\FamilyController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\HouseholdSettingsController;
use App\Http\Controllers\JoinController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\TransferController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::get('dashboard', function (Request $request) {
    $user = $request->user();
    $household = $user->households()->whereKey($user->current_household_id)->first()
        ?? $user->households()->first();

    if ($household) {
        return redirect()->route('households.home', $household);
    }

    // someone who scanned a QR code before registering carries on to joining
    return session()->has('join_code') ? redirect()->route('join.create') : redirect()->route('setup.create');
})->middleware(['auth', 'verified'])->name('dashboard');

// join
Route::get('join/{code}', [JoinController::class, 'show'])->name('join.show');
Route::middleware(['auth', 'verified'])
    ->group(function () {
        Route::controller(SetupController::class)
            ->prefix('setup')
            ->name('setup.')
            ->group(function () {
                Route::get('/', 'create')->name('create');
                Route::post('/', 'store')->name('store');
            });

        Route::controller(JoinController::class)
            ->prefix('join')
            ->name('join.')
            ->group(function () {
                Route::middleware('throttle:join')
                    ->group(function () {
                        Route::get('/', 'create')->name('create');
                        Route::post('/', 'store')->name('store');
                    });
            });
    });
// join end

// main app route group
Route::middleware(['auth', 'verified', 'household.member', 'throttle:household'])
    ->prefix('h/{household}')
    ->scopeBindings() // {budget} and {transaction} only resolve inside this household
    ->group(function () {
        // home
        Route::get('/', HomeController::class)->name('households.home');
        // home end

        // budget
        Route::controller(BudgetController::class)
            ->prefix('budgets')
            ->name('budgets.')
            ->group(function () {
                Route::get('/', 'edit')->name('edit');
                Route::put('/', 'update')->name('update');
                Route::get('/{budget}', 'show')->name('show');
            });
        // budget end

        // transaction
        Route::resource('transactions', TransactionController::class);
        // transaction end

        // transfer
        Route::controller(TransferController::class)
            ->prefix('transfers')
            ->name('transfers.')
            ->group(function () {
                Route::get('/create', 'create')->name('create');
                Route::post('/', 'store')->name('store');
                Route::delete('/{transfer}', 'destroy')->whereUuid('transfer')->name('destroy');
            });
        // transfer end

        // close month
        Route::controller(CloseMonthController::class)
            ->prefix('close')
            ->name('close.')
            ->group(function () {
                Route::get('/', 'show')->name('show');
                Route::post('/', 'store')->name('store');
            });
        // close month end

        // family
        Route::controller(FamilyController::class)
            ->middleware('throttle:invites')
            ->prefix('family')
            ->name('family.')
            ->group(function () {
                Route::get('/', 'show')->name('show');
                Route::post('/invite', 'invite')->name('invite');
                Route::patch('/members/{member}', 'update')->whereNumber('member');
                Route::delete('/members/{member}', 'destroy')->whereNumber('member');
            });
        // family end

        // household settings
        Route::controller(HouseholdSettingsController::class)
            ->prefix('settings')
            ->group(function () {
                Route::get('/', 'show')->name('household.settings');
                Route::put('/', 'update');
                Route::delete('/', 'destroy');
            });
        // household settings end

        // report
        Route::get('report', ReportController::class)->name('report');
        // report end
    });
// main app route end

Route::get('manifest.webmanifest', fn () => response()->json([
    'name' => config('app.name'),
    'short_name' => config('app.name'),
    'start_url' => '/dashboard',
    'scope' => '/',
    'display' => 'standalone',
    'orientation' => 'portrait',
    'background_color' => '#ffffff',
    'theme_color' => '#18181b',
    'icons' => [
        ['src' => '/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png'],
        ['src' => '/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png'],
        ['src' => '/icons/icon-512-maskable.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
    ],
], options: JSON_UNESCAPED_SLASHES)->header('Content-Type', 'application/manifest+json'));

require __DIR__.'/settings.php';
