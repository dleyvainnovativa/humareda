<?php

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

Route::get('/ping', fn () => response()->json(['pong' => true]));
