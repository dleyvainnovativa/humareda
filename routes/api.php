<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API routes
|--------------------------------------------------------------------------
| The WhatsApp webhook is registered here in T2. It is intentionally
| unauthenticated (Meta can't send a bearer token) and is protected instead
| by the verify-token challenge (GET) and X-Hub-Signature-256 check (POST).
|
| Example (T2):
|   Route::get('/webhook/whatsapp',  [WebhookController::class, 'verify']);
|   Route::post('/webhook/whatsapp', [WebhookController::class, 'handle']);
|
| Token-protected admin API (optional) uses the 'firebase' middleware:
|   Route::middleware('firebase')->group(function () { ... });
*/

Route::get('/ping', fn () => response()->json(['pong' => true]));
