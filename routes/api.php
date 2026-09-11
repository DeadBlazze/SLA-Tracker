<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AmoOAuthController;
use App\Http\Controllers\AmoWebhookController;


Route::get('/', function (){return 'AmoCRM SLA tracker';});

Route::get('/oauth/callback', [AmoOAuthController::class, 'callback']);

Route::prefix('webhooks/amo')->group(function () {
    // Единый эндпоинт для обработки всех вебхуков (смена статуса, создание сделки)
    Route::post('/', [AmoWebhookController::class, 'handle']);

    // Либо раздельные маршруты, если решите слать разные вебхуки из настроек воронки:
    // Route::post('/lead-started', [AmoWebhookController::class, 'leadStarted']);
    // Route::post('/lead-finished', [AmoWebhookController::class, 'leadFinished']);
});