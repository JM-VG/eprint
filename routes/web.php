<?php

use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\ShiftHistoryController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\StudentApprovalController;
use App\Http\Controllers\Admin\UserAccountController;
use App\Http\Controllers\Api\AvailabilityController;
use App\Http\Controllers\Api\HeartbeatController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Staff\StationController;
use Illuminate\Support\Facades\Route;

/*
| EnsureApproved and EnsurePasswordChanged run on every web request (bootstrap/app.php),
| so a disabled account or a temporary password is caught on any page.
*/

Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'));

Route::get('/api/availability', AvailabilityController::class)
    ->middleware('throttle:600,1')->name('api.availability');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::post('/api/heartbeat', HeartbeatController::class)
        ->middleware('role:staff,admin')->name('api.heartbeat');

    // Student pages (print and photo jobs) are added in Phase 4.
    Route::middleware('role:student')->prefix('student')->name('student.')->group(function () {
        //
    });

    // Counter pages for staff; admins do everything staff do. EnsureStation joins in Phase 3.
    Route::middleware('role:staff,admin')->prefix('staff')->name('staff.')->group(function () {
        Route::get('/station', [StationController::class, 'show'])->name('station');
        Route::post('/station', [StationController::class, 'store'])->name('station.store');
        Route::post('/station/switch', [StationController::class, 'switch'])->name('station.switch');
        Route::post('/station/clock-out', [StationController::class, 'clockOut'])->name('station.clock-out');

        // Job-processing pages go inside this group.
        Route::middleware('station')->group(function () {
            //
        });
    });

    // Admin-only pages: approvals, staff, prices, settings, reports, payouts.
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/students', [StudentApprovalController::class, 'index'])->name('students.index');
        Route::post('/students/{user}/approve', [StudentApprovalController::class, 'approve'])->name('students.approve');
        Route::post('/students/{user}/reject', [StudentApprovalController::class, 'reject'])->name('students.reject');

        Route::get('/shifts', [ShiftHistoryController::class, 'index'])->name('shifts.index');

        Route::get('/services', [ServiceController::class, 'index'])->name('services.index');
        Route::get('/services/create', [ServiceController::class, 'create'])->name('services.create');
        Route::post('/services', [ServiceController::class, 'store'])->name('services.store');
        Route::get('/services/{service}/edit', [ServiceController::class, 'edit'])->name('services.edit');
        Route::put('/services/{service}', [ServiceController::class, 'update'])->name('services.update');

        Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');

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
