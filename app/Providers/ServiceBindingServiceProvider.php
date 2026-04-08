<?php
namespace App\Providers;

use App\Services\Contracts\FaqServiceInterface;
use App\Services\Contracts\MenuManagerServiceInterface;
use App\Services\Contracts\MenuServiceInterface;
use App\Services\Contracts\NoticeServiceInterface;
use App\Services\Contracts\TranslationServiceInterface;
use Illuminate\Support\ServiceProvider;

// === Interfaces ===
use App\Services\Contracts\AuthServiceInterface;

class ServiceBindingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // 인터페이스 ↔ 버전별 구현체 매핑 (키는 반드시 소문자: v1, v2)
        $this->bindVersioned(AuthServiceInterface::class, [
            'v1' => \App\Services\V1\AuthService::class
        ]);

        $this->bindVersioned(MenuServiceInterface::class, [
            'v1' => \App\Services\V1\MenuService::class
        ]);

        $this->bindVersioned(MenuManagerServiceInterface::class, [
            'v1' => \App\Services\V1\MenuManagerService::class
        ]);

        $this->bindVersioned(TranslationServiceInterface::class, [
            'v1' => \App\Services\V1\TranslationService::class
        ]);

        $this->bindVersioned(NoticeServiceInterface::class, [
            'v1' => \App\Services\V1\NoticeService::class
        ]);

        $this->bindVersioned(FaqServiceInterface::class, [
            'v1' => \App\Services\V1\FaqService::class
        ]);
    }

    /**
     * 인터페이스를 요청 버전에 맞는 구현체로 바인딩한다.
     * 우선순위: 헤더(X-Api-Version) > 라우트(/api/v{n}/) > 설정(default_service_impl)
     */
    private function bindVersioned(string $interface, array $map): void
    {
        // 맵 키를 소문자로 정규화 (예방적)
        $normalizedMap = [];
        foreach ($map as $k => $v) {
            $normalizedMap[strtolower($k)] = $v;
        }

        $this->app->bind($interface, function ($app) use ($normalizedMap, $interface) {
            // CLI에서도 안전: Request 존재 여부 체크
            $request = $app->bound('request') ? $app->make('request') : null;

            $version = null;

            // 1) Header 우선
            if ($request) {
                $header = $request->headers->get('X-Api-Version');
                if ($header) {
                    $version = $header;
                }
            }

            // 2) /api/v{n}/ prefix 추론
            if (!$version && $request) {
                $path = $request->path() ?? '';
                if (preg_match('#^api/(v\d+)/#i', $path, $m)) {
                    $version = $m[1]; // e.g. v1, v2
                }
            }

            // 3) 설정값 fallback (없으면 v1)
            if (!$version) {
                $version = config('services.default_service_impl', 'v1');
            }

            // 소문자 정규화 후 매핑
            $version = strtolower($version);
            $impl = $normalizedMap[$version] ?? ($normalizedMap['v1'] ?? reset($normalizedMap));

            if (!class_exists($impl)) {
                throw new \RuntimeException("Implementation not found for {$interface}: {$impl}");
            }

            return $app->make($impl);
        });
    }
}
