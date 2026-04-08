<?php

namespace App\Http\Controllers\Admin\V1\Setting;

use App\Http\Controllers\Controller;
use App\Services\Contracts\MenuManagerServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenuManagerController extends Controller
{
    public function __construct(
        private MenuManagerServiceInterface $menuManagerService
    ) {
        parent::__construct();
    }

    /**
     * 메뉴 목록 조회
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'nullable|in:gnb,snb',
            'parent_id' => 'required_if:type,snb|integer',
            'lang' => 'nullable|in:KO,EN,JA,ZH,ZH-TW,ES',
        ]);

        return $this->handleServiceCall(function() use ($validated) {
            $type = $validated['type'] ?? 'gnb'; // 기본값 gnb
            $parentId = $validated['parent_id'] ?? null;
            $lang = $validated['lang'] ?? 'KO'; // 기본값 대문자 KO

            if ($type === 'snb' && $parentId) {
                return $this->menuManagerService->getSnbByGnb((int)$parentId, $lang);
            } else {
                return $this->menuManagerService->getAllGnb($lang);
            }
        });
    }

    /**
     * 메뉴 상세 조회
     */
    public function show(Request $request, int $id): JsonResponse
    {
        // Route parameter 검증
        $this->validateRouteId($id);

        // Query parameter 검증
        $validated = $request->validate([
            'type' => 'required|in:gnb,snb',
        ]);

        return $this->handleServiceCall(function() use ($id, $validated) {
            $type = $validated['type'];

            $menu = null;
            if ($type === 'snb') {
                $menu = $this->menuManagerService->getSnbDetail($id);
            } else {
                $menu = $this->menuManagerService->getGnbDetail($id);
            }

            return $menu;
        });
    }

    /**
     * 메뉴 생성
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:gnb,snb',
            'title' => 'required|string|max:100',
            'parent_seq' => 'required_if:type,snb|integer',
            'url' => 'nullable|string|max:255',
            'view_yn' => 'nullable|in:Y,N',
            'translations' => 'nullable|array',
        ]);

        $type = $validated['type'];

        return $this->handleServiceCall(function() use ($request, $type) {
            if ($type === 'snb') {
                return $this->menuManagerService->createSnb($request->all());
            } else {
                return $this->menuManagerService->createGnb($request->all());
            }
        });
    }

    /**
     * 메뉴 개별 수정
     */
    public function update(Request $request, int $id): JsonResponse
    {
        // Route parameter 검증
        $this->validateRouteId($id);

        // Body parameter 검증
        $validated = $request->validate([
            'type' => 'required|in:gnb,snb',
            'parent_seq' => 'required_if:type,snb|integer',
            'title' => 'required|string|max:100',
            'url' => 'nullable|string|max:255',
            'view_yn' => 'nullable|in:Y,N',
            'translations' => 'nullable|array',
        ]);

        return $this->handleServiceCall(function() use ($id, $request, $validated) {
            $type = $validated['type'];

            if ($type === 'snb') {
                return $this->menuManagerService->updateSnb($id, $request->all());
            } else {
                return $this->menuManagerService->updateGnb($id, $request->all());
            }
        });
    }

    /**
     * 메뉴 순서 변경
     */
    public function updateOrder(Request $request): JsonResponse
    {
        // Body parameter 검증
        $validated = $request->validate([
            'type' => 'required|in:gnb,snb',
            'orders' => 'required|array',
            'orders.*.seq' => 'required|integer|min:1',
            'orders.*.order_no' => 'required|integer|min:1',
        ]);

        return $this->handleServiceCall(function() use ($validated) {
            $type = $validated['type'];
            $orders = $validated['orders'];

            if ($type === 'snb') {
                $this->menuManagerService->updateSnbOrder($orders);
            } else {
                $this->menuManagerService->updateGnbOrder($orders);
            }
            return [];
        });
    }

    /**
     * 메뉴 삭제
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        // Route parameter 검증
        $this->validateRouteId($id);

        // Query parameter 검증
        $validated = $request->validate([
            'type' => 'required|in:gnb,snb',
        ]);

        return $this->handleServiceCall(function() use ($id, $validated) {
            $type = $validated['type'];

            if ($type === 'snb') {
                $this->menuManagerService->deleteSnb($id);
            } else {
                $this->menuManagerService->deleteGnb($id);
            }

            return [];
        });
    }
}
