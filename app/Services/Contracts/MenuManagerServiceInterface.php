<?php

namespace App\Services\Contracts;

interface MenuManagerServiceInterface
{
    /**
     * GNB 전체 목록 조회
     *
     * @param  string  $lang  언어 코드
     */
    public function getAllGnb(string $lang = 'ko'): array;

    /**
     * GNB 상세 조회
     *
     * @param  int  $mgSeq
     */
    public function getGnbDetail(int $mg_seq): ?array;

    /**
     * GNB 생성
     */
    public function createGnb(array $data): array;

    /**
     * GNB 수정
     *
     * @param  int  $mgSeq
     */
    public function updateGnb(int $mg_seq, array $data): array;

    /**
     * GNB 삭제 (soft delete)
     *
     * @param  int  $mgSeq
     */
    public function deleteGnb(int $mg_seq): bool;

    /**
     * GNB 순서 변경
     */
    public function updateGnbOrder(array $orders): bool;

    /**
     * 특정 GNB의 SNB 목록 조회
     *
     * @param  int  $mgSeq
     * @param  string  $lang  언어 코드
     */
    public function getSnbByGnb(int $mg_seq, string $lang = 'ko'): array;

    /**
     * SNB 상세 조회
     *
     * @param  int  $msSeq
     */
    public function getSnbDetail(int $ms_seq): ?array;

    /**
     * SNB 생성
     */
    public function createSnb(array $data): array;

    /**
     * SNB 수정
     *
     * @param  int  $msSeq
     */
    public function updateSnb(int $ms_seq, array $data): array;

    /**
     * SNB 삭제 (soft delete)
     */
    public function deleteSnb(int $msSeq): bool;

    /**
     * SNB 순서 변경
     */
    public function updateSnbOrder(array $orders): bool;
}
