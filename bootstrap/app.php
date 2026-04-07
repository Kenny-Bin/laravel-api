<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up'
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // CORS 미들웨어 추가 (가장 먼저 실행)
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);

        // SQL 에러 자동 로깅 미들웨어 (API 요청에만 적용)
        $middleware->api(append: [
            \App\Http\Middleware\LogSqlErrors::class,
        ]);

        $middleware->append(\App\Http\Middleware\InjectSessionFromHeaders::class);
        $middleware->append(\App\Http\Middleware\QuickEcho::class);

        // 미들웨어 별칭 등록
        $middleware->alias([
            'verify.client' => \App\Http\Middleware\VerifyClientAccess::class,
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Controller에서 처리하지 못한 Exception만 처리
        $exceptions->render(function (\Throwable $exception, \Illuminate\Http\Request $request) {
            // ValidationException을 통일된 형식으로 변환
            if ($exception instanceof \Illuminate\Validation\ValidationException) {
                \Illuminate\Support\Facades\Log::warning('Validation failed', [
                    'errors' => $exception->errors(),
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                    'input' => $request->all()
                ]);

                // API 요청인 경우 통일된 형식으로 응답
                if ($request->expectsJson() || $request->is('api/*')) {
                    $errors = $exception->errors();

                    return response()->json([
                        'status' => 'fail',
                        'data' => [
                            'code' => 'VALIDATION_ERROR',
                            'errors' => $errors // 상세 에러 정보도 포함
                        ],
                        'source' => 'api'
                    ], 422);
                }

                return null; // 웹 요청은 Laravel 기본 처리
            }

            // Controller에서 catch하지 못한 Exception만 처리
            if ($request->expectsJson() || $request->is('api/*')) {
                // SQL 에러인 경우 DB에 로깅
                \App\Services\SqlErrorLogService::logIfQueryException($exception, $request->fullUrl());

                \Illuminate\Support\Facades\Log::error('Uncaught exception', [
                    'exception' => get_class($exception),
                    'message' => $exception->getMessage(),
                    'url' => $request->fullUrl(),
                    'method' => $request->method()
                ]);

                $statusCode = method_exists($exception, 'getStatusCode') ? $exception->getStatusCode() : 500;

                return response()->json([
                    'status' => 'fail',
                    'data' => [
                        'code' => 'UNKNOWN_ERROR',
                    ],
                    'source' => 'api'
                ], $statusCode);
            }

            return null;
        });
    })->create();
