<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AmoOAuthController;
use App\Http\Controllers\AmoWebhookController;
use App\Http\Controllers\MegafonWebhookController;
use App\Http\Controllers\testController;

Route::get('/', fn () => response()->json(['Данный enpoint не поддерживается в настоящее время']));

Route::post('/oauth/callback', [AmoOAuthController::class, 'callback']);

Route::prefix('webhooks/amo')->group(function () {
    // Единый эндпоинт для обработки всех вебхуков (смена статуса, создание сделки)
    Route::post('/', [AmoWebhookController::class, 'handle']);
});

// Единый эндпоинт для МегаФон АТС
Route::post('/webhooks/megafon', [MegafonWebhookController::class, 'handle']);

Route::post('/test', [testController::class, 'index']);