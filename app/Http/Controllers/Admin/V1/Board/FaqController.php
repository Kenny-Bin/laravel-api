<?php

namespace App\Http\Controllers\Admin\V1\Board;

use App\Http\Controllers\Controller;
use App\Services\Contracts\FaqServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Traits\HasPagination;

class FaqController extends Controller
{

    use HasPagination;

    public function __construct(
        private FaqServiceInterface $faqService
    ) {
        parent::__construct();
    }

    /**
     * FAQ 목록 조회 (페이징)
     *
     */
    public function index(Request $request): JsonResponse
    {

        ['page' => $page, 'per_page' => $perPage] = $this->getPaginationParams($request);

        $validated = $request->validate([
            'search' => 'nullable|string|max:200',
            'faq_kind' => 'nullable|integer|min:1',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        return $this->handleServiceCall(function() use ($page, $perPage, $validated) {
            // 검색 필터 (null 값 제외)
            $filters = array_filter($validated, fn($value) => !is_null($value));

            return $this->faqService->getFaqList($page, $perPage, $filters);
        });
    }

    /**
     * FAQ 상세 조회
     * GET /api/v1/faq/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        // Route parameter 검증
        $this->validateRouteId($id);

        return $this->handleServiceCall(function() use ($id) {
            $faq = $this->faqService->getFaqDetail($id);
            return $faq;
        });
    }

    /**
     * FAQ 생성
     * POST /api/v1/faq
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'faq_kind' => 'required|integer',
            'is_view' => 'required|in:1,0',
            'ask' => 'nullable|array',
            'answer' => 'nullable|array',
        ]);

        return $this->handleServiceCall(function() use ($request) {
            return $this->faqService->createFaq($request->all());
        });
    }

    /**
     * FAQ 수정
     * PUT /api/v1/faq
     */
    public function update(Request $request, int $id): JsonResponse
    {
        // Route parameter 검증
        $this->validateRouteId($id);

        $validated = $request->validate([
            'faq_kind' => 'required',
            'is_view' => 'required|in:1,0',
            'ask' => 'nullable|array',
            'answer' => 'nullable|array',
        ]);

        return $this->handleServiceCall(function() use ($id, $request, $validated) {
            return $this->faqService->updatefaq($id, $request->all());
        });
    }

    /**
     * FAQ 수정
     * DELETE /api/v1/faq
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        // Route parameter 검증
        $this->validateRouteId($id);

        return $this->handleServiceCall(function() use ($id) {
            return $this->faqService->deletefaq($id);
        });
    }
}
