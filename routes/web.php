<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HousekeepingController;
use App\Http\Controllers\HousekeepingReportController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PublicBookingController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\ReservationWorkflowController;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicBookingController::class, 'index'])->name('home');
Route::post('/pre-reservations', [PublicBookingController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('pre-reservations.store');
Route::get('/pre-reservations/{reference}/confirmation', [PublicBookingController::class, 'confirmation'])
    ->where('reference', '[A-Za-z0-9-]+')
    ->name('pre-reservations.confirmation');

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('login.post');
Route::get('/register', fn () => redirect()->route('login'))->name('register');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

Route::middleware(['auth'])->group(function (): void {
    Route::middleware([RoleMiddleware::class.':admin,recepcion'])->group(function (): void {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/reservations/create', [ReservationController::class, 'create'])->name('reservations.create');
        Route::post('/reservations', [ReservationController::class, 'store'])->name('reservations.store');

        Route::post('/reservations/{reservation}/confirm', [ReservationWorkflowController::class, 'confirm'])
            ->name('reservations.confirm');
        Route::post('/reservations/{reservation}/check-in', [ReservationWorkflowController::class, 'checkIn'])
            ->name('reservations.check-in');
        Route::post('/reservations/{reservation}/check-out', [ReservationWorkflowController::class, 'checkOut'])
            ->name('reservations.check-out');
        Route::post('/reservations/{reservation}/cancel', [ReservationWorkflowController::class, 'cancel'])
            ->name('reservations.cancel');
        Route::post('/reservations/{reservation}/no-show', [ReservationWorkflowController::class, 'noShow'])
            ->name('reservations.no-show');
        Route::post('/reservations/{reservation}/relocate', [ReservationWorkflowController::class, 'relocate'])
            ->name('reservations.relocate');

        Route::post('/reservations/{reservation}/payments', [PaymentController::class, 'store'])
            ->name('payments.store');
        Route::patch('/payments/{payment}', [PaymentController::class, 'updateStatus'])
            ->name('payments.update');
    });

    Route::middleware([RoleMiddleware::class.':admin'])->prefix('admin')->name('admin.')->group(function (): void {
        Route::get('/', [AdminController::class, 'index'])->name('index');
        Route::post('/branches', [AdminController::class, 'storeBranch'])->name('branches.store');
        Route::patch('/branches/{branch}', [AdminController::class, 'updateBranch'])->name('branches.update');
        Route::patch('/branches/{branch}/rates/{category}', [AdminController::class, 'updateRoomRate'])
            ->name('branches.rates.update');
        Route::post('/users', [AdminController::class, 'storeUser'])->name('users.store');
        Route::patch('/users/{user}', [AdminController::class, 'updateUser'])->name('users.update');
        Route::post('/exchange-rates', [AdminController::class, 'storeExchangeRate'])->name('exchange-rates.store');
    });

    Route::middleware([RoleMiddleware::class.':admin,limpieza,recepcion'])->group(function (): void {
        Route::get('/housekeeping', [HousekeepingController::class, 'index'])->name('housekeeping.index');
        Route::patch('/housekeeping/rooms/{room}', [HousekeepingController::class, 'updateStatus'])->name('housekeeping.update');
        Route::post('/housekeeping/rooms/{room}/reports', [HousekeepingReportController::class, 'store'])
            ->name('housekeeping.reports.store');
        Route::post('/housekeeping/reports/{report}/resolve', [HousekeepingReportController::class, 'resolve'])
            ->name('housekeeping.reports.resolve');
    });
});
