<?php

use App\Http\Controllers\MenuController;
use App\Http\Controllers\Admin\V1\Auth\AuthController;
use App\Http\Controllers\Admin\V1\Setting\MenuManagerController;
use App\Http\Controllers\Admin\V1\Board\NoticeController;
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

    // ==================== 설정 ====================

    // 메뉴 API (기존)
    Route::prefix('menus')->group(function () {
        Route::get('/', [MenuController::class, 'index']);
        Route::get('check-update', [MenuController::class, 'checkUpdate']);
    });

    // 메뉴 관리
    Route::prefix('menu-manager')->group(function () {
        Route::get('/', [MenuManagerController::class, 'index']);
        Route::get('{id}', [MenuManagerController::class, 'show'])->where('id', '[0-9]+');
        Route::post('/', [MenuManagerController::class, 'store']);
        Route::put('order', [MenuManagerController::class, 'updateOrder']);
        Route::put('{id}', [MenuManagerController::class, 'update'])->where('id', '[0-9]+');
        Route::delete('{id}', [MenuManagerController::class, 'destroy'])->where('id', '[0-9]+');
    });

    // ==================== 공지 및 서비스 ====================

    // 공지사항
    Route::prefix('notice')->group(function () {
        Route::get('/', [NoticeController::class, 'index']);
        Route::get('{id}', [NoticeController::class, 'show'])->where('id', '[0-9]+');
        Route::post('/', [NoticeController::class, 'store']);
        Route::put('{id}', [NoticeController::class, 'update'])->where('id', '[0-9]+');
        Route::delete('{id}', [NoticeController::class, 'destroy'])->where('id', '[0-9]+');
    });

    // FAQ
    Route::prefix('faq')->group(function () {
        Route::get('/', [FaqController::class, 'index']);
        Route::get('{id}', [FaqController::class, 'show'])->where('id', '[0-9]+');
        Route::post('/', [FaqController::class, 'store']);
        Route::put('{id}', [FaqController::class, 'update'])->where('id', '[0-9]+');
        Route::delete('{id}', [FaqController::class, 'destroy'])->where('id', '[0-9]+');
    });

    // ==================== 번역 ====================

    // 번역 API
    Route::prefix('translation')->group(function () {
        Route::post('/', [TranslationController::class, 'translate']);
        Route::post('chatgpt', [TranslationController::class, 'translateWithChatGPT']);
        Route::post('deepl', [TranslationController::class, 'translateWithDeepL']);
    });


});
