<?php

namespace App\Helpers;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;

class JwtHelper
{
    /**
     * JWT 토큰 생성
     *
     * @param array $payload 토큰에 담을 데이터
     * @return string JWT 토큰
     */
    public static function encode(array $payload): string
    {
        $secret = env('JWT_SECRET');
        $expiration = env('JWT_EXPIRATION', 3600);

        // 기본 클레임 추가
        $payload['iat'] = time(); // 발급 시간
        $payload['exp'] = time() + $expiration; // 만료 시간

        return JWT::encode($payload, $secret, 'HS256');
    }

    /**
     * JWT 토큰 검증 및 디코딩
     *
     * @param string $token JWT 토큰
     * @return object|null 디코딩된 페이로드 또는 null
     */
    public static function decode(string $token): ?object
    {
        try {
            $secret = env('JWT_SECRET');
            $decoded = JWT::decode($token, new Key($secret, 'HS256'));
            return $decoded;
        } catch (ExpiredException $e) {
            // 토큰 만료
            return null;
        } catch (SignatureInvalidException $e) {
            // 서명 불일치 (조작됨)
            return null;
        } catch (\Exception $e) {
            // 기타 에러
            return null;
        }
    }

    /**
     * Bearer 토큰에서 JWT 추출
     *
     * @param string $authHeader Authorization 헤더 값
     * @return string|null JWT 토큰 또는 null
     */
    public static function extractToken(string $authHeader): ?string
    {
        if (!str_starts_with($authHeader, 'Bearer ')) {
            return null;
        }

        return substr($authHeader, 7); // "Bearer " 제거
    }
}
