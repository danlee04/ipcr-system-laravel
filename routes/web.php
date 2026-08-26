<?php

use App\Http\Controllers\Admin\DesignationController;
use App\Http\Controllers\Admin\DivisionController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\JobFunctionController;
use App\Http\Controllers\Admin\JobTitleController;
use App\Http\Controllers\Admin\PeriodController;
use App\Http\Controllers\Admin\PositionController;
use App\Http\Controllers\Admin\SectionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\IpcrApprovalController;
use App\Http\Controllers\IpcrController;
use App\Http\Controllers\IpcrItemController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/ipcrs', [IpcrController::class, 'index'])->name('ipcrs.index');
    Route::get('/ipcrs/create', [IpcrController::class, 'create'])->name('ipcrs.create');
    Route::post('/ipcrs', [IpcrController::class, 'store'])->name('ipcrs.store');
    Route::get('/ipcrs/{ipcr}', [IpcrController::class, 'show'])->name('ipcrs.show');
    Route::delete('/ipcrs/{ipcr}', [IpcrController::class, 'destroy'])->name('ipcrs.destroy');
    Route::post('/ipcrs/{ipcr}/submit', [IpcrController::class, 'submit'])->name('ipcrs.submit');

    /*
     * The approval side. Guarded by IpcrPolicy rather than a role: who may
     * act is decided per IPCR by the chain stamped on it at submission, not
     * by anything global about the user.
     */
    Route::get('/approvals', [IpcrApprovalController::class, 'inbox'])->name('approvals.inbox');
    Route::put('/ipcrs/{ipcr}/ratings', [IpcrApprovalController::class, 'updateRatings'])->name('ipcrs.ratings.update');
    Route::post('/ipcrs/{ipcr}/assess', [IpcrApprovalController::class, 'assess'])->name('ipcrs.assess');
    Route::post('/ipcrs/{ipcr}/approve', [IpcrApprovalController::class, 'approve'])->name('ipcrs.approve');
    Route::post('/ipcrs/{ipcr}/return', [IpcrApprovalController::class, 'returnForRevision'])->name('ipcrs.return');

    Route::post('/ipcrs/{ipcr}/items', [IpcrItemController::class, 'store'])->name('ipcrs.items.store');
    Route::put('/ipcrs/{ipcr}/items/{item}', [IpcrItemController::class, 'update'])->name('ipcrs.items.update');
    Route::delete('/ipcrs/{ipcr}/items/{item}', [IpcrItemController::class, 'destroy'])->name('ipcrs.items.destroy');
});

/*
 * Admin area.
 *
 * Protection lives here on the group, not on individual controllers: a new
 * admin route is then protected by default rather than one forgotten line away
 * from being open.
 *
 * `verified` is deliberately absent. MustVerifyEmail is commented out on the
 * User model, so email verification is not enforced anywhere in this app and
 * including it here would imply a guarantee that does not exist.
 */
Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'role:admin|hr'])
    ->group(function () {
        Route::get('/divisions', [DivisionController::class, 'index'])->name('divisions.index');
        Route::get('/job-titles', [JobTitleController::class, 'index'])->name('job-titles.index');
        Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
        Route::get('/periods', [PeriodController::class, 'index'])->name('periods.index');
        Route::get('/job-functions', [JobFunctionController::class, 'index'])->name('job-functions.index');

        Route::post('/divisions', [DivisionController::class, 'store'])->name('divisions.store');
        Route::put('/divisions/{division}', [DivisionController::class, 'update'])->name('divisions.update');
        Route::patch('/divisions/{division}/active', [DivisionController::class, 'setActive'])->name('divisions.active');
        Route::delete('/divisions/{division}', [DivisionController::class, 'destroy'])->name('divisions.destroy');

        Route::post('/sections', [SectionController::class, 'store'])->name('sections.store');
        Route::put('/sections/{section}', [SectionController::class, 'update'])->name('sections.update');
        Route::patch('/sections/{section}/active', [SectionController::class, 'setActive'])->name('sections.active');
        Route::delete('/sections/{section}', [SectionController::class, 'destroy'])->name('sections.destroy');

        Route::post('/positions', [PositionController::class, 'store'])->name('positions.store');
        Route::put('/positions/{position}', [PositionController::class, 'update'])->name('positions.update');
        Route::patch('/positions/{position}/active', [PositionController::class, 'setActive'])->name('positions.active');
        Route::delete('/positions/{position}', [PositionController::class, 'destroy'])->name('positions.destroy');

        Route::post('/designations', [DesignationController::class, 'store'])->name('designations.store');
        Route::put('/designations/{designation}', [DesignationController::class, 'update'])->name('designations.update');
        Route::patch('/designations/{designation}/active', [DesignationController::class, 'setActive'])->name('designations.active');
        Route::delete('/designations/{designation}', [DesignationController::class, 'destroy'])->name('designations.destroy');

        Route::post('/employees', [EmployeeController::class, 'store'])->name('employees.store');
        Route::put('/employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
        Route::patch('/employees/{employee}/active', [EmployeeController::class, 'setActive'])->name('employees.active');
        Route::delete('/employees/{employee}', [EmployeeController::class, 'destroy'])->name('employees.destroy');

        Route::post('/periods', [PeriodController::class, 'store'])->name('periods.store');
        Route::put('/periods/{period}', [PeriodController::class, 'update'])->name('periods.update');
        Route::patch('/periods/{period}/status', [PeriodController::class, 'setStatus'])->name('periods.status');
        Route::delete('/periods/{period}', [PeriodController::class, 'destroy'])->name('periods.destroy');

        Route::post('/job-functions', [JobFunctionController::class, 'store'])->name('job-functions.store');
        Route::put('/job-functions/{jobFunction}', [JobFunctionController::class, 'update'])->name('job-functions.update');
        Route::patch('/job-functions/{jobFunction}/active', [JobFunctionController::class, 'setActive'])->name('job-functions.active');
        Route::delete('/job-functions/{jobFunction}', [JobFunctionController::class, 'destroy'])->name('job-functions.destroy');
    });

require __DIR__ . '/auth.php';
