<?php

use App\Http\Controllers\Api\ConversationApiController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\ReservationApiController;
use App\Http\Controllers\Webhook\InstagramWebhookController;
use App\Http\Controllers\Webhook\MessengerWebhookController;
use App\Http\Controllers\Webhook\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API routes
|--------------------------------------------------------------------------
| The WhatsApp webhook is intentionally unauthenticated (Meta can't send a
| bearer token). It is protected by the verify-token challenge (GET) and the
| X-Hub-Signature-256 HMAC check (POST). Full URL: /api/webhook/whatsapp
*/

Route::get('/webhook/whatsapp',  [WhatsAppWebhookController::class, 'verify']);
Route::post('/webhook/whatsapp', [WhatsAppWebhookController::class, 'handle']);

Route::get('/webhook/messenger',  [MessengerWebhookController::class, 'verify']);
Route::post('/webhook/messenger', [MessengerWebhookController::class, 'handle']);

Route::get('/webhook/instagram',  [InstagramWebhookController::class, 'verify']);
Route::post('/webhook/instagram', [InstagramWebhookController::class, 'handle']);

Route::get('/ping', fn () => response()->json(['pong' => true]));

/*
|--------------------------------------------------------------------------
| Staff app (Flutter) — T14 Phase 4
|--------------------------------------------------------------------------
| Stateless Firebase bearer auth. URLs are prefixed /api/app/*. These mirror
| the Blade panel's conversation + reservation logic (same 24h-window rule,
| same approve/reject services) for the mobile staff app.
*/
Route::middleware('firebase')->prefix('app')->group(function () {
    Route::get('/me', [MeController::class, 'me']);

    Route::get('/conversations',                       [ConversationApiController::class, 'index']);
    Route::get('/conversations/{contact}',             [ConversationApiController::class, 'show']);
    Route::get('/conversations/{contact}/poll',        [ConversationApiController::class, 'poll']);
    Route::post('/conversations/{contact}/reply',      [ConversationApiController::class, 'reply']);
    Route::post('/conversations/{contact}/take-over',  [ConversationApiController::class, 'takeOver']);
    Route::post('/conversations/{contact}/return-to-bot', [ConversationApiController::class, 'returnToBot']);

    Route::get('/reservations',                        [ReservationApiController::class, 'index']);
    Route::post('/reservations',                       [ReservationApiController::class, 'store']);
    Route::put('/reservations/{reservation}',          [ReservationApiController::class, 'update']);
    Route::post('/reservations/{reservation}/approve', [ReservationApiController::class, 'approve']);
    Route::post('/reservations/{reservation}/reject',  [ReservationApiController::class, 'reject']);
    Route::post('/reservations/{reservation}/cancel',  [ReservationApiController::class, 'cancel']);
});
