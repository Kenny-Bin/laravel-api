<?php

namespace App\Services\Contracts;

interface FaqServiceInterface
{
    /**
     * FAQ 목록 조회 (페이징)
     */
    public function getFaqList(int $page = 1, int $perPage = 20, array $filters = []): array;

    /**
     * FAQ 상세 조회
     */
    public function getFaqDetail(int $brd_faq_seq): array;

    /**
     * FAQ 생성
     */
    public function createFaq(array $data): array;

    /**
     * FAQ 수정
     */
    public function updateFaq(int $id, array $data): array;

    /**
     * FAQ 삭제
     */
    public function deleteFaq(int $id): bool;
}
