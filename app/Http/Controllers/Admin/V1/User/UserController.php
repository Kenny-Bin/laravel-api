<?php

namespace App\Http\Controllers\Admin\V1\User;

use App\Http\Controllers\Controller;
use App\Services\Contracts\UserServiceInterface;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * UserController
 *
 * 회원 API 컨트롤러
 *
 * 엔드포인트:
 * - GET    /api/v1/user                      - 채널 목록 조회 (페이징)
 * - GET    /api/v1/user/{id}                 - 채널 상세 조회
 * - PUT    /api/v1/user/{id}                 - 채널 수정
 */

class UserController extends Controller
{
    use HasPagination;

    public function __construct(
        private UserServiceInterface $userService
    ) {
        parent::__construct();
    }

    /**
     *  회원 목록 조회 (페이징)
     */
    public function index(Request $request): JsonResponse
    {
        ['page' => $page, 'per_page' => $perPage] = $this->getPaginationParams($request);

        $validated = $request->validate([
            'search' => 'nullable|string|max:200',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'status' => 'nullable|integer',
            'gender' => 'nullable|string|in:M,F',
            'nat_cd' => 'nullable|string',
            'keyword' => 'nullable|string|max:200'
        ]);

        return $this->handleServiceCall(function() use ($page, $perPage, $validated) {
            // 검색 필터 (null 값 제외)
            $filters = array_filter($validated, fn($value) => !is_null($value));

            return $this->userService->getUserList($page, $perPage, $filters);
        });
    }

    /**
     * 회원 상세 조회
     * GET /api/v1/user/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        // Route parameter 검증
        $this->validateRouteId($id);

        return $this->handleServiceCall(function() use ($id) {
            return $this->userService->getUserDetail($id);
        });
    }

    /**
     * 회원 수정
     * PUT /api/v1/user/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        // Route parameter 검증
        $this->validateRouteId($id);

        $validated = $request->validate([
            'country_code' => 'required|integer',
            'nationality_type' => 'required|string',
            'phone_number' => 'required|numeric',
            'sex' => 'required|string',
        ]);

        return $this->handleServiceCall(function() use ($id, $request, $validated) {
            return $this->userService->updateUser($id, $request->all());
        });
    }
}
