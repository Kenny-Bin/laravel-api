<?php

namespace App\Http\Controllers;

use App\Services\Contracts\MenuServiceInterface;
use Illuminate\Http\JsonResponse;

class MenuController extends Controller
{
    public function __construct(
        private MenuServiceInterface $menuService
    ) {
        parent::__construct();
    }

    /**
     * 메뉴 전체 조회 (GNB + SNB)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(\Illuminate\Http\Request $request): JsonResponse
    {
        return $this->handleServiceCall(function() use ($request) {
            $lang = $request->query('lang', 'KO');
            return $this->menuService->getAllMenus($lang);
        });
    }

    /**
     * 메뉴 업데이트 확인 (마지막 수정 시간 체크)
     */
    public function checkUpdate(): JsonResponse
    {
        return $this->handleServiceCall(function() {
            return [
                'last_updated' => $this->menuService->getLastUpdatedTimestamp(),
            ];
        });
    }
}
