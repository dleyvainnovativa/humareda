<?php

use App\Http\Controllers\Api\AppConversationController;
use App\Http\Controllers\Api\AppController;
use App\Http\Controllers\Api\AppDeviceController;
use App\Http\Controllers\Api\AppReservationController;
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
| Mobile app API (additive) — staff Flutter app
|--------------------------------------------------------------------------
| Stateless Firebase bearer auth via the existing `firebase` middleware alias
| (Authorization: Bearer <firebase-id-token>). Self-contained; does not touch
| the webhook/bot pipeline above. Full prefix: /api/app/*
*/
Route::middleware('firebase')->prefix('app')->group(function () {
    Route::get('/me',      [AppController::class, 'me']);
    Route::get('/summary', [AppController::class, 'summary']);

    Route::get('/conversations',                     [AppConversationController::class, 'index']);
    Route::get('/conversations/{contact}/messages',  [AppConversationController::class, 'messages']);
    Route::post('/conversations/{contact}/reply',    [AppConversationController::class, 'reply']);

    Route::get('/reservations', [AppReservationController::class, 'index']);

    Route::post('/devices',            [AppDeviceController::class, 'store']);
    Route::delete('/devices',          [AppDeviceController::class, 'destroy']);
});
