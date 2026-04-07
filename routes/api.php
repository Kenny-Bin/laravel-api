<?php

use App\Http\Controllers\Admin\V1\Auth\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (v1)
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // ==================== 인증 ====================

    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthController::class, 'login']);
    });
});
