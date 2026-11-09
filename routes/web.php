<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\CloseMonthController;
use App\Http\Controllers\FamilyController;
use App\Http\Controllers\GoalController;
use App\Http\Controllers\GoalMovementController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\HouseholdSettingsController;
use App\Http\Controllers\JoinController;
use App\Http\Controllers\NotificationSettingsController;
use App\Http\Controllers\OpenHouseholdSettings;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\TransferController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::get('dashboard', function (Request $request) {
    $user = $request->user();
    $household = $user->currentOrFirstHousehold();

    if ($household) {
        return redirect()->route('households.home', $household);
    }

    return session()->has('join_code')
        ? redirect()->route('join.create')
        : redirect()->route('setup.create');
})->middleware(['auth', 'verified'])->name('dashboard');

// Join & Setup Routes
Route::get('join/{code}', [JoinController::class, 'show'])->name('join.show');
Route::middleware(['auth', 'verified'])->group(function () {
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
            Route::middleware('throttle:join')->group(function () {
                Route::get('/', 'create')->name('create');
                Route::post('/', 'store')->name('store');
            });
        });

    Route::controller(PushSubscriptionController::class)
        ->prefix('push')
        ->group(function () {
            Route::post('subscribe', 'store');
            Route::delete('subscribe', 'destroy');
            Route::delete('devices/{id}', 'remove')->whereNumber('id');
            Route::post('test', 'test')->middleware('throttle:6,1');
        });
});

// Main Household App Group
Route::middleware(['auth', 'verified', 'household.member', 'throttle:household'])
    ->prefix('h/{household}')
    ->scopeBindings()
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

        Route::resource('transactions', TransactionController::class)->except(['create', 'edit', 'show']);

        Route::controller(GoalController::class)
            ->prefix('goals')
            ->name('goals.')
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::patch('/{goal}', 'update')->name('update');
                Route::post('/{goal}/complete', 'complete')->name('complete');
            });

        Route::controller(GoalMovementController::class)
            ->prefix('goals/{goal}/movements')
            ->name('goals.movements.')
            ->group(function () {
                Route::post('/', 'store')->name('store');
                Route::delete('/{movement}', 'destroy')->name('destroy');
            });

        Route::controller(TransferController::class)
            ->prefix('transfers')
            ->name('transfers.')
            ->group(function () {
                Route::post('/', 'store')->name('store');
                Route::delete('/{transfer}', 'destroy')->whereUuid('transfer')->name('destroy');
            });

        Route::controller(CloseMonthController::class)
            ->prefix('close')
            ->name('close.')
            ->group(function () {
                Route::get('/', 'show')->name('show');
                Route::post('/', 'store')->name('store');
            });

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

        // Settings Routes Group
        Route::prefix('settings')->group(function () {
            // Main Household Settings Page
            Route::get('/', [HouseholdSettingsController::class, 'show'])->name('household.settings');
            Route::put('/', [HouseholdSettingsController::class, 'update']);
            Route::delete('/', [HouseholdSettingsController::class, 'destroy']);
            Route::patch('/notifications', [NotificationSettingsController::class, 'update']);

            // Profile Routes (Directly mapped to AccountController for update/destroy)
            Route::get('/profile', [OpenHouseholdSettings::class, 'profile'])->name('profile.edit');
            Route::patch('/profile', [AccountController::class, 'update'])->name('profile.update');
            Route::delete('/profile', [AccountController::class, 'destroy'])->name('profile.destroy');

            // Security Routes
            Route::get('/security', [SecurityController::class, 'edit'])
                ->middleware(RequirePassword::class)
                ->name('security.edit');

            Route::put('/password', [SecurityController::class, 'update'])
                ->middleware('throttle:6,1')
                ->name('user-password.update');

            // Appearance Routes
            Route::get('/appearance', [OpenHouseholdSettings::class, 'appearance'])->name('appearance.edit');
        });

        Route::get('report', ReportController::class)->name('report');
    });

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
