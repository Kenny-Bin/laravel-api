<?php

namespace App\Services\V1;

use App\Models\Admin\BoardFaq;
use App\Services\BaseService;
use App\Services\Contracts\FaqServiceInterface;
use App\Traits\HasPaginationResponse;

class FaqService extends BaseService implements FaqServiceInterface
{
    use HasPaginationResponse;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * FAQ 목록 조회 (페이징)
     *
     * @param int $page 페이지 번호
     * @param int $perPage 페이지당 항목 수
     * @param array $filters 검색 필터 (search, status 등)
     * @return array
     */
    public function getFaqList(int $page = 1, int $perPage = 20, array $filters = []): array
    {
        $query = BoardFaq::where('is_active', true);

        // 검색 필터 적용
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->whereRaw("ask_language::text ILIKE ?", ["%{$search}%"])
                    ->orWhereRaw("answer_language::text ILIKE ?", ["%{$search}%"]);
            });
        }

        // 구분 필터
        if (!empty($filters['faq_kind'])) {
            $query->where('scmenu_seq', $filters['faq_kind']);
        }

        // 날짜 범위 필터 (create_ts, 한국시간 기준)
        if (!empty($filters['date_from'])) {
            $query->whereRaw("DATE(create_ts AT TIME ZONE 'UTC' AT TIME ZONE 'Asia/Seoul') >= ?", [$filters['date_from']]);
        }

        if (!empty($filters['date_to'])) {
            $query->whereRaw("DATE(create_ts AT TIME ZONE 'UTC' AT TIME ZONE 'Asia/Seoul') <= ?", [$filters['date_to']]);
        }

        // 전체 개수 조회
        $total = $query->count();

        // 페이징 적용
        $items = $query->orderBy('create_ts', 'desc')
            ->orderBy('board_faq_seq', 'desc')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get()
            ->toArray();

        return $this->buildPaginationResponse($items, $total, $page, $perPage);
    }

    /**
     * FAQ 상세 조회
     *
     * @param int $brd_faq_seq FAQ 번호
     * @return array
     * @throws \Exception
     */
    public function getFaqDetail(int $brd_faq_seq): array
    {
        $faq = BoardFaq::where('board_faq_seq', $brd_faq_seq)
            ->where('is_active', true)
            ->first();

        if (!$faq) {
            throw new \Exception(json_encode([
                'code' => 'FAQ_NULL',
                'message' => ''
            ]));
        }

        return $faq->toArray();
    }

    /**
     * FAQ 생성
     *
     * @param array $data
     * @return array
     * @throws \Exception
     */
    public function createFaq(array $data): array
    {
        return $this->executeInTransaction(function () use ($data) {

            $boardFaq = BoardFaq::create([
                'faq_kind' => $data['faq_kind'],
                'ask_language' => $data['ask'] ?? [],
                'answer_language' => $data['answer'] ?? [],
                'is_view' => (int) $data['is_view'],
            ]);

            return $boardFaq->toArray();
        });

    }
    /**
     * FAQ 수정
     *
     * @param int $id
     * @param array $data
     * @return array
     * @throws \Exception
     */
    public function updateFaq(int $id, array $data): array
    {
        return $this->executeInTransaction(function () use ($id, $data) {

            $faq = BoardFaq::find($id);

            if (!$faq) {
                throw new \Exception(json_encode([
                    'code' => 'FAQ_NULL',
                    'message' => ''
                ]));
            }

            $faq->update([
                'faq_kind' => $data['faq_kind'],
                'ask_language' => $data['ask'] ?? [],
                'answer_language' => $data['answer'] ?? [],
                'is_view' => (int) $data['is_view'],
            ]);

            return $faq->fresh()->toArray();
        });
    }

    /**
     * FAQ 삭제
     *
     * @param int $id
     * @return bool
     * @throws \Exception
     */
    public function deleteFaq(int $id): bool
    {
        return $this->executeInTransaction(function () use ($id) {

            $faq = BoardFaq::find($id);

            if (!$faq) {
                throw new \Exception(json_encode([
                    'code' => 'FAQ_NULL',
                    'message' => ''
                ]));
            }

            $faq->update([
                'is_active' => false,
            ]);

            return true;
        });
    }
}
