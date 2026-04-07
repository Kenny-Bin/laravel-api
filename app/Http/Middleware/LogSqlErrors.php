<?php

namespace App\Http\Middleware;

use App\Services\SqlErrorLogService;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

/**
 * SQL 에러 자동 로깅 Middleware
 *
 * 모든 요청을 감싸서 QueryException이 발생하면 자동으로 DB에 로깅
 * Controller/Service에서 catch하지 않아도 이 Middleware가 잡아서 로깅
 */
class LogSqlErrors
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        try {
            // 요청 처리
            return $next($request);

        } catch (QueryException $e) {
            // SQL 에러 발생 시 자동으로 DB에 로깅
            SqlErrorLogService::log($e, $request->fullUrl());

            // 에러를 다시 throw해서 정상적인 에러 처리 흐름 유지
            throw $e;
        }
    }
}
