<?php

use App\Http\Controllers\Admin\V1\Auth\AuthController;
use App\Http\Controllers\Admin\V1\Translation\TranslationController;
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

    // ==================== 번역 ====================

    // 번역 API
    Route::prefix('translation')->group(function () {
        Route::post('/', [TranslationController::class, 'translate']);
        Route::post('chatgpt', [TranslationController::class, 'translateWithChatGPT']);
        Route::post('deepl', [TranslationController::class, 'translateWithDeepL']);
    });


});
