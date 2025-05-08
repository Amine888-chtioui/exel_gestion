<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductionStopController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Production Stops API routes
Route::prefix('production-stops')->group(function () {
    Route::get('/', [ProductionStopController::class, 'index']);
    Route::get('/dashboard-stats', [ProductionStopController::class, 'dashboardStats']);
    Route::get('/filter-options', [ProductionStopController::class, 'getFilterOptions']);
    Route::post('/import', [ProductionStopController::class, 'importExcel']);
});