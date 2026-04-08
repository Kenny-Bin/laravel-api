<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 전달된 헤더 정보를 Request attributes에 저장
 * API 서버는 stateless여야 하므로 session 대신 request attributes 사용
 */
class InjectSessionFromHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // X-Admin-Seq 헤더가 있으면 request attributes에 설정
        if ($request->hasHeader('X-Admin-Seq')) {
            $adminSeq = (int) $request->header('X-Admin-Seq');
            $request->attributes->set('admin_seq', $adminSeq);
        }

        // 필요시 다른 헤더도 처리 가능
        // if ($request->hasHeader('X-Admin-Id')) {
        //     $adminId = $request->header('X-Admin-Id');
        //     $request->attributes->set('admin_id', $adminId);
        // }

        return $next($request);
    }
}
