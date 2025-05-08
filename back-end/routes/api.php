<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ExcelFileController;

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