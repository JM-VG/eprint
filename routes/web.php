<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

/*
| EnsureApproved and EnsurePasswordChanged run on every web request (bootstrap/app.php),
| so a disabled account or a temporary password is caught on any page.
*/

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Student pages (print and photo jobs) are added in Phase 4.
    Route::middleware('role:student')->prefix('student')->name('student.')->group(function () {
        //
    });

    // Counter pages for staff; admins do everything staff do. EnsureStation joins in Phase 3.
    Route::middleware('role:staff,admin')->prefix('staff')->name('staff.')->group(function () {
        //
    });

    // Admin-only pages: approvals, staff, prices, settings, reports, payouts.
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        //
    });
});

require __DIR__.'/auth.php';
