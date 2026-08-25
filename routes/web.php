<?php

use App\Http\Controllers\Admin\DivisionController;
use App\Http\Controllers\Admin\JobTitleController;
use App\Http\Controllers\Admin\SectionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\IpcrController;
use App\Http\Controllers\IpcrItemController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

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
    ->middleware(['auth', 'role:admin'])
    ->group(function () {
        Route::get('/divisions', [DivisionController::class, 'index'])->name('divisions.index');
        Route::get('/job-titles', [JobTitleController::class, 'index'])->name('job-titles.index');

        Route::post('/divisions', [DivisionController::class, 'store'])->name('divisions.store');
        Route::put('/divisions/{division}', [DivisionController::class, 'update'])->name('divisions.update');
        Route::patch('/divisions/{division}/active', [DivisionController::class, 'setActive'])->name('divisions.active');
        Route::delete('/divisions/{division}', [DivisionController::class, 'destroy'])->name('divisions.destroy');

        Route::post('/sections', [SectionController::class, 'store'])->name('sections.store');
        Route::put('/sections/{section}', [SectionController::class, 'update'])->name('sections.update');
        Route::patch('/sections/{section}/active', [SectionController::class, 'setActive'])->name('sections.active');
        Route::delete('/sections/{section}', [SectionController::class, 'destroy'])->name('sections.destroy');
    });

require __DIR__ . '/auth.php';
