<?php

use App\Http\Controllers\Auth\FirebaseLoginController;
use App\Http\Controllers\ConversationController;
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

    // T6: live conversation panel (replaces the T2 read-only log)
    Route::get('/conversations', [ConversationController::class, 'index'])->name('conversations.index');
    Route::get('/conversations/{contact}', [ConversationController::class, 'show'])->name('conversations.show');
    Route::get('/conversations/{contact}/poll', [ConversationController::class, 'poll'])->name('conversations.poll');
    Route::post('/conversations/{contact}/reply', [ConversationController::class, 'reply'])->name('conversations.reply');
    Route::post('/conversations/{contact}/take-over', [ConversationController::class, 'takeOver'])->name('conversations.takeover');
    Route::post('/conversations/{contact}/return-to-bot', [ConversationController::class, 'returnToBot'])->name('conversations.return');

    // Future tiers:
    //   T8  -> /knowledge   (KB editor)
    //   T10 -> /reservations (CRUD), /settings (capacity + reminders)
});
