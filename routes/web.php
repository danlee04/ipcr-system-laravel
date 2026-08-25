<?php

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
    Route::post('/ipcrs/{ipcr}/submit', [IpcrController::class, 'submit'])->name('ipcrs.submit');

    Route::post('/ipcrs/{ipcr}/items', [IpcrItemController::class, 'store'])->name('ipcrs.items.store');
    Route::put('/ipcrs/{ipcr}/items/{item}', [IpcrItemController::class, 'update'])->name('ipcrs.items.update');
    Route::delete('/ipcrs/{ipcr}/items/{item}', [IpcrItemController::class, 'destroy'])->name('ipcrs.items.destroy');
});

require __DIR__ . '/auth.php';
