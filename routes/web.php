<?php

use App\Http\Controllers\Auth\FirebaseLoginController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\KnowledgeController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web routes (session-based, Blade admin panel)
|--------------------------------------------------------------------------
*/

Route::get('/', fn () => redirect()->route('dashboard'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [FirebaseLoginController::class, 'show'])->name('login');
});
Route::post('/auth/firebase', [FirebaseLoginController::class, 'authenticate'])->name('auth.firebase');
Route::post('/logout', [FirebaseLoginController::class, 'logout'])->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Reservations (staff) — T11 adds approve/reject for the pending queue
    Route::get('/reservations', [ReservationController::class, 'index'])->name('reservations.index');
    Route::post('/reservations', [ReservationController::class, 'store'])->name('reservations.store');
    Route::put('/reservations/{reservation}', [ReservationController::class, 'update'])->name('reservations.update');
    Route::post('/reservations/{reservation}/approve', [ReservationController::class, 'approve'])->name('reservations.approve');
    Route::post('/reservations/{reservation}/reject', [ReservationController::class, 'reject'])->name('reservations.reject');
    Route::post('/reservations/{reservation}/cancel', [ReservationController::class, 'cancel'])->name('reservations.cancel');

    // Live conversation panel (T6)
    Route::get('/conversations', [ConversationController::class, 'index'])->name('conversations.index');
    Route::get('/conversations/{contact}', [ConversationController::class, 'show'])->name('conversations.show');
    Route::get('/conversations/{contact}/poll', [ConversationController::class, 'poll'])->name('conversations.poll');
    Route::post('/conversations/{contact}/reply', [ConversationController::class, 'reply'])->name('conversations.reply');
    Route::post('/conversations/{contact}/take-over', [ConversationController::class, 'takeOver'])->name('conversations.takeover');
    Route::post('/conversations/{contact}/return-to-bot', [ConversationController::class, 'returnToBot'])->name('conversations.return');

    // Knowledge base (T8)
    Route::get('/knowledge', [KnowledgeController::class, 'index'])->name('knowledge.index');
    Route::post('/knowledge', [KnowledgeController::class, 'store'])->name('knowledge.store');
    Route::put('/knowledge/{entry}', [KnowledgeController::class, 'update'])->name('knowledge.update');
    Route::post('/knowledge/{entry}/toggle', [KnowledgeController::class, 'toggle'])->name('knowledge.toggle');
    Route::delete('/knowledge/{entry}', [KnowledgeController::class, 'destroy'])->name('knowledge.destroy');

    // Menú (T13) — datos estructurados del menú; el bot cotiza desde aquí
    Route::get('/menu', [MenuController::class, 'index'])->name('menu.index');
    Route::post('/menu/categories', [MenuController::class, 'storeCategory'])->name('menu.categories.store');
    Route::put('/menu/categories/{category}', [MenuController::class, 'updateCategory'])->name('menu.categories.update');
    Route::post('/menu/categories/{category}/toggle', [MenuController::class, 'toggleCategory'])->name('menu.categories.toggle');
    Route::delete('/menu/categories/{category}', [MenuController::class, 'destroyCategory'])->name('menu.categories.destroy');
    Route::post('/menu/items', [MenuController::class, 'storeItem'])->name('menu.items.store');
    Route::put('/menu/items/{item}', [MenuController::class, 'updateItem'])->name('menu.items.update');
    Route::post('/menu/items/{item}/toggle', [MenuController::class, 'toggleItem'])->name('menu.items.toggle');
    Route::delete('/menu/items/{item}', [MenuController::class, 'destroyItem'])->name('menu.items.destroy');

    // Settings (T10)
    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::post('/settings/hours', [SettingsController::class, 'updateHours'])->name('settings.hours');
    Route::post('/settings/general', [SettingsController::class, 'updateGeneral'])->name('settings.general');
});
