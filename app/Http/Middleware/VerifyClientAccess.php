<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Helpers\JwtHelper;

class VerifyClientAccess
{
    /**
     * Handle an incoming request.
     *
     * Client API 요청은 JWT 토큰으로 인증
     * - Authorization 헤더에서 JWT 추출
     * - JWT 검증 (서명, 만료 시간)
     * - cln_seq 추출하여 Request에 저장
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Authorization 헤더에서 JWT 추출
        $authHeader = $request->header('Authorization');

        \Log::debug('VerifyClientAccess Middleware Start', [
            'has_auth_header' => !empty($authHeader),
            'auth_header_preview' => $authHeader ? substr($authHeader, 0, 30) . '...' : null,
            'uri' => $request->getRequestUri(),
        ]);

        if (!$authHeader) {
            \Log::warning('VerifyClientAccess: No Authorization header');
            return response()->json([
                'status' => 'fail',
                'data' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'Authorization 헤더가 없습니다.',
                    'redirect' => '/client-login'
                ],
                'source' => 'api'
            ], 401);
        }

        $token = JwtHelper::extractToken($authHeader);

        if (!$token) {
            \Log::warning('VerifyClientAccess: Invalid Bearer token format');
            return response()->json([
                'status' => 'fail',
                'data' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'Bearer 토큰 형식이 올바르지 않습니다.',
                    'redirect' => '/client-login'
                ],
                'source' => 'api'
            ], 401);
        }

        // 2. JWT 검증 및 디코딩
        $payload = JwtHelper::decode($token);

        \Log::debug('VerifyClientAccess JWT Decoded', [
            'payload_exists' => !empty($payload),
            'payload' => $payload ? (array)$payload : null,
        ]);

        if (!$payload) {
            \Log::warning('VerifyClientAccess: JWT decode failed');
            return response()->json([
                'status' => 'fail',
                'data' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => '토큰이 만료되었거나 유효하지 않습니다.',
                    'redirect' => '/client-login'
                ],
                'source' => 'api'
            ], 401);
        }

        // 3. cln_seq 확인
        if (!isset($payload->cln_seq)) {
            \Log::warning('VerifyClientAccess: No cln_seq in JWT payload', [
                'payload' => (array)$payload
            ]);
            return response()->json([
                'status' => 'fail',
                'data' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => '토큰에 Client 정보가 없습니다.',
                    'redirect' => '/client-login'
                ],
                'source' => 'api'
            ], 401);
        }

        // 4. Request에 cln_seq 저장 (컨트롤러에서 사용)
        $request->attributes->set('cln_seq', (int)$payload->cln_seq);
        $request->attributes->set('client_email', $payload->email ?? null);

        // 5. X-Timezone 헤더 파싱 (Client용 timezone)
        $timezone = $request->header('X-Timezone', 'Asia/Seoul');
        $request->attributes->set('client_timezone', $timezone);

        // input으로도 접근 가능하도록 merge (하위 호환성)
        $request->merge([
            '_cln_seq' => (int)$payload->cln_seq,
            '_client_email' => $payload->email ?? null,
            '_client_timezone' => $timezone,
        ]);

        \Log::debug('VerifyClientAccess Success', [
            'cln_seq' => (int)$payload->cln_seq,
            'email' => $payload->email ?? null,
            'timezone' => $timezone,
        ]);

        return $next($request);
    }
}
