<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AmoOAuthController;
use App\Http\Controllers\AmoWebhookController;
use App\Http\Controllers\MegafonWebhookController;
use App\Http\Controllers\Dashboard\testController;
use App\Http\Controllers\Dashboard\AuthController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Middleware\JWTAuthMiddleware;
use App\Http\Middleware\CheckRoleMiddleware;

Route::get('/', fn () => response()->json(['Данный enpoint не поддерживается в настоящее время']));

Route::post('/dashboard/login', [AuthController::class, 'login']);
Route::post('/dashboard/get-managers-activity', [DashboardController::class, 'getManagersActivity'])
->middleware(['jwt', 'role:1,2']);

Route::post('/test', [testController::class, 'index'])
->middleware(['jwt', 'role:1,2,3']);

// Amowebhooks
Route::post('/oauth/callback', [AmoOAuthController::class, 'callback']);
Route::prefix('/webhooks/amo')->group(function () {
    // Единый эндпоинт для обработки всех вебхуков (смена статуса, создание сделки)
    Route::post('/', [AmoWebhookController::class, 'handle']);
});

// МегаФон АТС
Route::post('/webhooks/megafon', [MegafonWebhookController::class, 'handle']);
