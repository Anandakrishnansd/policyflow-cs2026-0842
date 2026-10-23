<?php

use App\Http\Controllers\Api\MockInsurerController;
use App\Http\Controllers\Api\PolicyRequestController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| PolicyFlow REST API Routes
|--------------------------------------------------------------------------
| Implements the mandatory evaluation API contract:
| - POST /api/policy-requests
| - GET  /api/policy-requests/{id}
| - POST /api/policy-requests/{id}/submit
| - POST /api/mock-insurer/callback
| - GET  /api/policy-requests
| Plus reconciliation and status query endpoints.
|--------------------------------------------------------------------------
*/

Route::prefix('policy-requests')->group(function () {
    Route::post('/', [PolicyRequestController::class, 'store']);
    Route::get('/', [PolicyRequestController::class, 'index']);
    Route::get('/{id}', [PolicyRequestController::class, 'show']);
    Route::post('/{id}/submit', [PolicyRequestController::class, 'submit']);
    Route::post('/{id}/reconcile', [PolicyRequestController::class, 'reconcile']);
});

Route::prefix('mock-insurer')->group(function () {
    Route::post('/callback', [MockInsurerController::class, 'callback']);
    Route::get('/policies/{provider_reference}', [MockInsurerController::class, 'lookup']);
    Route::post('/trigger-callback/{provider_reference}', [MockInsurerController::class, 'triggerCallback']);
});
