<?php

use App\Http\Controllers\Auth\FirebaseLoginController;
use App\Http\Controllers\ConversationLogController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web routes (session-based, Blade admin panel)
|--------------------------------------------------------------------------
*/

Route::get('/', fn () => redirect()->route('dashboard'));

// --- Auth (Firebase -> Laravel session) -------------------------------------
Route::middleware('guest')->group(function () {
    Route::get('/login', [FirebaseLoginController::class, 'show'])->name('login');
});
Route::post('/auth/firebase', [FirebaseLoginController::class, 'authenticate'])->name('auth.firebase');
Route::post('/logout', [FirebaseLoginController::class, 'logout'])->name('logout');

// --- Authenticated admin panel ----------------------------------------------
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // T2: read-only message log (T6 upgrades to live chat + handoff)
    Route::get('/conversations', [ConversationLogController::class, 'index'])->name('conversations.index');
    Route::get('/conversations/{contact}', [ConversationLogController::class, 'show'])->name('conversations.show');

    // Future tiers:
    //   T8  -> /knowledge   (KB editor)
    //   T10 -> /reservations (CRUD), /settings (capacity + reminders)
});
