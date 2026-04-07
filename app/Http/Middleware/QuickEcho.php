<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class QuickEcho
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var \Symfony\Component\HttpFoundation\Response $response */
        $response = $next($request);

        // prod OFF
        $enabled = app()->environment() !== 'production';

        // 트리거: ?__echo 또는 X-Debug: echo
        $triggered = $request->has('__echo') || $request->header('X-Debug') === 'echo';

        if (! $enabled || ! $triggered) {
            return $response;
        }

        // 기본 정보
        $user = $request->user();
        $echo = [
            'route'  => optional($request->route())->uri(),
            'method' => $request->method(),
            'user'   => $user ? ['id' => $user->id, 'email' => $user->email] : 'guest',
        ];

        // ✅ 서비스/컨트롤러에서 넣어둔 attributes 병합
        //    예: request()->attributes->set('quick_echo', ['auth.login.user' => ...]);
        $extra = $request->attributes->get('quick_echo', []);
        if (!empty($extra)) {
            $echo['extra'] = $extra;
        }

        $echoStr = 'echo=' . json_encode($echo, JSON_UNESCAPED_UNICODE);

        // 헤더로만 출력 (JSON 파싱 안전)
        $response->headers->set('X-Echo', $echoStr);

        $ctype = $response->headers->get('Content-Type', '');

        // JSON 응답인 경우 _debug 필드 추가 (상태코드는 유지)
        if (str_contains($ctype, 'application/json') && $response instanceof \Illuminate\Http\JsonResponse) {
            $originalStatusCode = $response->getStatusCode();
            $data = $response->getData(true);

            // 로그로 확인

            $data['_debug'] = ['x_echo' => $echo];
            $response->setData($data);
            $response->setStatusCode($originalStatusCode); // 원래 상태코드 복원

        }
        // HTML/plain이면 주석으로 보조 출력
        elseif (str_contains($ctype, 'text/html')) {
            $response->setContent($response->getContent() . "\n<!-- {$echoStr} -->");
        } elseif (str_contains($ctype, 'text/plain')) {
            $response->setContent($response->getContent() . "\n# {$echoStr}");
        }

        return $response;
    }
}
