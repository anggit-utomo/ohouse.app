<?php

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

Route::middleware(['auth', 'verified', 'household.member'])
    ->prefix('h/{household}')
    ->scopeBindings() // {budget} and {transaction} only resolve inside this household
    ->group(function () {
        // phase 2 routes go here
    });

require __DIR__ . '/settings.php';
