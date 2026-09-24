<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AmoOAuthController;
use App\Http\Controllers\AmoWebhookController;


Route::get('/', fn () => phpinfo());

Route::post('/oauth/callback', [AmoOAuthController::class, 'callback']);

Route::prefix('webhooks/amo')->group(function () {
    // Единый эндпоинт для обработки всех вебхуков (смена статуса, создание сделки)
    Route::post('/', [AmoWebhookController::class, 'handle']);
});