<?php

use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\StudentApprovalController;
use App\Http\Controllers\Admin\UserAccountController;
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
        Route::get('/students', [StudentApprovalController::class, 'index'])->name('students.index');
        Route::post('/students/{user}/approve', [StudentApprovalController::class, 'approve'])->name('students.approve');
        Route::post('/students/{user}/reject', [StudentApprovalController::class, 'reject'])->name('students.reject');

        Route::get('/staff', [StaffController::class, 'index'])->name('staff.index');
        Route::get('/staff/create', [StaffController::class, 'create'])->name('staff.create');
        Route::post('/staff', [StaffController::class, 'store'])->name('staff.store');
        Route::get('/staff/{user}/edit', [StaffController::class, 'edit'])->name('staff.edit');
        Route::put('/staff/{user}', [StaffController::class, 'update'])->name('staff.update');

        Route::post('/users/{user}/disable', [UserAccountController::class, 'disable'])->name('users.disable');
        Route::post('/users/{user}/enable', [UserAccountController::class, 'enable'])->name('users.enable');
        Route::post('/users/{user}/reset-password', [UserAccountController::class, 'resetPassword'])->name('users.reset-password');
    });
});

require __DIR__.'/auth.php';
