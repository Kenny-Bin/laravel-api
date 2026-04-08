<?php

namespace App\Http\Controllers\Admin\V1\Board;

use App\Http\Controllers\Controller;
use App\Services\Contracts\NoticeServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Traits\HasPagination;

class NoticeController extends Controller
{
    use HasPagination;

    public function __construct(
        private NoticeServiceInterface $noticeService
    ) {
        parent::__construct();
    }

    /**
     * 공지사항 목록 조회 (페이징)
     */
    public function index(Request $request): JsonResponse
    {

        ['page' => $page, 'per_page' => $perPage] = $this->getPaginationParams($request);

        $validated = $request->validate([
            'search' => 'nullable|string|max:200',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'is_view' => 'nullable|in:0,1',
            'is_top' => 'nullable|in:0,1'
        ]);

        return $this->handleServiceCall(function() use ($page, $perPage, $validated) {
            // 검색 필터 (null 값 제외)
            $filters = array_filter($validated, fn($value) => !is_null($value));
            
            return $this->noticeService->getNoticeList($page, $perPage, $filters);
        });
    }

    /**
     * 공지사항 상세 조회
     */
    public function show(Request $request, int $id): JsonResponse
    {
        // Route parameter 검증
        $this->validateRouteId($id);

        return $this->handleServiceCall(function() use ($id) {
            $notice = $this->noticeService->getNoticeDetail($id);
            return $notice;
        });
    }

    /**
     * 공지사항 생성
     * POST /api/v1/notice
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject_language' => 'required',
            'content_language' => 'required',
            'is_top' => 'nullable',
            'is_view' => 'nullable',
            'attachment.*' => 'nullable|file|max:10240', // 다국어 파일 (10MB)
        ]);

        // JSON 문자열을 배열로 변환 (multipart로 전송된 경우)
        if (is_string($validated['subject_language'])) {
            $validated['subject_language'] = json_decode($validated['subject_language'], true) ?? [];
        }
        if (is_string($validated['content_language'])) {
            $validated['content_language'] = json_decode($validated['content_language'], true) ?? [];
        }
        if (isset($validated['is_top'])) {
            $validated['is_top'] = filter_var($validated['is_top'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }
        if (isset($validated['is_view'])) {
            $validated['is_view'] = filter_var($validated['is_view'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        // 파일 업로드 처리
        if ($request->hasFile('attachment')) {
            $validated['attachment'] = $request->file('attachment');
        }

        return $this->handleServiceCall(function() use ($validated) {
            return $this->noticeService->createNotice($validated);
        });
    }

    /**
     * 공지사항 수정
     */
    public function update(Request $request, int $id): JsonResponse
    {
        // Route parameter 검증
        $this->validateRouteId($id);

        // Body parameter 검증
        $validated = $request->validate([
            'subject_language' => 'required',
            'content_language' => 'required',
            'is_top' => 'nullable',
            'is_view' => 'nullable',
            'attachment.*' => 'nullable|file|max:10240', // 다국어 파일 (10MB)
            'deleted_attachment_languages' => 'nullable|array', // 삭제할 파일 언어 코드 배열
        ]);

        // JSON 문자열을 배열로 변환 (multipart로 전송된 경우)
        if (is_string($validated['subject_language'])) {
            $validated['subject_language'] = json_decode($validated['subject_language'], true) ?? [];
        }
        if (is_string($validated['content_language'])) {
            $validated['content_language'] = json_decode($validated['content_language'], true) ?? [];
        }

        // boolean 값 처리
        if (isset($validated['is_top'])) {
            $validated['is_top'] = filter_var($validated['is_top'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }
        if (isset($validated['is_view'])) {
            $validated['is_view'] = filter_var($validated['is_view'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        // 파일 업로드 처리
        if ($request->hasFile('attachment')) {
            $validated['attachment'] = $request->file('attachment');
        }

        return $this->handleServiceCall(function() use ($id, $validated) {
            return $this->noticeService->updateNotice($id, $validated);
        });
    }

    /**
     * 공지사항 삭제
     * DELETE /api/v1/notice/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        // Route parameter 검증
        $this->validateRouteId($id);

        return $this->handleServiceCall(function() use ($id) {
            $this->noticeService->deleteNotice($id);
            return [];
        });
    }
}
