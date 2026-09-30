<?php

use App\Http\Controllers\Admin\PayoutController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\ShiftHistoryController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\StudentApprovalController;
use App\Http\Controllers\Admin\UserAccountController;
use App\Http\Controllers\Api\AvailabilityController;
use App\Http\Controllers\Api\HeartbeatController;
use App\Http\Controllers\Api\PriceEstimateController;
use App\Http\Controllers\Api\QueueCountController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Staff\ClaimController;
use App\Http\Controllers\Staff\CommissionController;
use App\Http\Controllers\Staff\JobActionController;
use App\Http\Controllers\Staff\QueueController;
use App\Http\Controllers\Staff\StationController;
use App\Http\Controllers\Staff\SubmitJobController;
use App\Http\Controllers\Staff\WalkinSaleController;
use App\Http\Controllers\Student\DocumentJobController;
use App\Http\Controllers\Student\JobController;
use App\Http\Controllers\Student\PhotoJobController;
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

    Route::get('/files/jobs/{job}', [FileController::class, 'show'])->name('files.jobs.show');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::get('/api/notifications/unread-count', [NotificationController::class, 'count'])->name('api.notifications.count');

    Route::post('/api/price-estimate', PriceEstimateController::class)
        ->middleware('throttle:60,1')->name('api.price-estimate');

    Route::post('/api/heartbeat', HeartbeatController::class)
        ->middleware('role:staff,admin')->name('api.heartbeat');

    Route::get('/api/queue-count', QueueCountController::class)
        ->middleware('role:staff,admin')->name('api.queue-count');

    // Student pages (print and photo jobs) are added in Phase 4.
    Route::middleware('role:student')->prefix('student')->name('student.')->group(function () {
        Route::get('/jobs/create', [DocumentJobController::class, 'create'])->name('jobs.create');
        Route::post('/jobs', [DocumentJobController::class, 'store'])->name('jobs.store');
        Route::get('/jobs', [JobController::class, 'index'])->name('jobs.index');
        Route::get('/jobs/{job}', [JobController::class, 'show'])->name('jobs.show');
        Route::get('/jobs/{job}/edit', [JobController::class, 'edit'])->name('jobs.edit');
        Route::put('/jobs/{job}', [JobController::class, 'update'])->name('jobs.update');
        Route::delete('/jobs/{job}', [JobController::class, 'destroy'])->name('jobs.destroy');
        Route::get('/photos/create', [PhotoJobController::class, 'create'])->name('photos.create');
        Route::post('/photos', [PhotoJobController::class, 'store'])->name('photos.store');
    });

    // Counter pages for staff; admins do everything staff do. EnsureStation joins in Phase 3.
    Route::middleware('role:staff,admin')->prefix('staff')->name('staff.')->group(function () {
        Route::get('/station', [StationController::class, 'show'])->name('station');
        Route::post('/station', [StationController::class, 'store'])->name('station.store');
        Route::post('/station/switch', [StationController::class, 'switch'])->name('station.switch');
        Route::post('/station/clock-out', [StationController::class, 'clockOut'])->name('station.clock-out');

        // Own jobs (source staff_job) do not need an open station.
        Route::get('/jobs/create', [SubmitJobController::class, 'create'])->name('jobs.create');
        Route::post('/jobs', [SubmitJobController::class, 'store'])->name('jobs.store');
        Route::get('/commission', [CommissionController::class, 'index'])->name('commission');

        // Job-processing pages go inside this group.
        Route::middleware('station')->group(function () {
            Route::get('/queue', [QueueController::class, 'index'])->name('queue');
            Route::post('/jobs/{job}/accept', [JobActionController::class, 'accept'])->name('jobs.accept');
            Route::post('/jobs/{job}/reject', [JobActionController::class, 'reject'])->name('jobs.reject');
            Route::post('/jobs/{job}/printing', [JobActionController::class, 'printing'])->name('jobs.printing');
            Route::post('/jobs/{job}/ready', [JobActionController::class, 'ready'])->name('jobs.ready');
            Route::get('/claim', [ClaimController::class, 'show'])->name('claim');
            Route::post('/claim', [ClaimController::class, 'store'])->name('claim.store');
            Route::get('/walk-in', [WalkinSaleController::class, 'create'])->name('walkin.create');
            Route::post('/walk-in', [WalkinSaleController::class, 'store'])->name('walkin.store');
        });
    });

    // Admin-only pages: approvals, staff, prices, settings, reports, payouts.
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/students', [StudentApprovalController::class, 'index'])->name('students.index');
        Route::post('/students/{user}/approve', [StudentApprovalController::class, 'approve'])->name('students.approve');
        Route::post('/students/{user}/reject', [StudentApprovalController::class, 'reject'])->name('students.reject');

        Route::get('/shifts', [ShiftHistoryController::class, 'index'])->name('shifts.index');

        Route::get('/payouts', [PayoutController::class, 'index'])->name('payouts.index');
        Route::post('/payouts', [PayoutController::class, 'store'])->name('payouts.store');
        Route::get('/payouts/{payout}', [PayoutController::class, 'show'])->name('payouts.show');

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
