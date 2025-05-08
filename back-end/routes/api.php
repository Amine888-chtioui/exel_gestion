<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ExcelFileController;
use App\Http\Controllers\CrossAnalysisController;

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

// Excel files routes
Route::prefix('excel-files')->group(function () {
    Route::get('/', [ExcelFileController::class, 'index']);
    Route::post('/', [ExcelFileController::class, 'store']);
    Route::get('/{id}', [ExcelFileController::class, 'show']);
    Route::delete('/{id}', [ExcelFileController::class, 'destroy']);
});

// Cross-column analysis routes
Route::prefix('cross-analysis')->group(function () {
    // Get analysis between two columns
    Route::get('/{fileId}/{sheetName}', [CrossAnalysisController::class, 'getAvailableColumns']);
    Route::get('/{fileId}/{sheetName}/{targetColumn}/{sourceColumn}', [CrossAnalysisController::class, 'analyze']);
    
    // Special analyses
    Route::post('/{fileId}/{sheetName}/correlationMatrix', [CrossAnalysisController::class, 'getCorrelationMatrix']);
    Route::post('/{fileId}/{sheetName}/pivotStats', [CrossAnalysisController::class, 'getPivotStatistics']);
});

// Custom queries for advanced analysis
Route::prefix('custom-analysis')->group(function () {
    Route::post('/filter', [ExcelFileController::class, 'filterData']);
    Route::post('/groupby', [ExcelFileController::class, 'groupData']);
    Route::post('/calculate', [ExcelFileController::class, 'calculateCustomMetrics']);
});

// Export routes
Route::prefix('export')->group(function () {
    Route::get('/csv/{fileId}/{sheetName}', [ExcelFileController::class, 'exportCsv']);
    Route::get('/excel/{fileId}/{sheetName}', [ExcelFileController::class, 'exportExcel']);
    Route::post('/charts', [ExcelFileController::class, 'exportCharts']);
});

// Configuration routes
Route::prefix('config')->group(function () {
    Route::get('/', [ExcelFileController::class, 'getConfig']);
    Route::post('/', [ExcelFileController::class, 'updateConfig']);
});