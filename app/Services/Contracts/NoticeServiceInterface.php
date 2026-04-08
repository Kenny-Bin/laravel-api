<?php

namespace App\Services\Contracts;

interface NoticeServiceInterface
{
    /**
     * 공지사항 목록 조회 (페이징)
     */
    public function getNoticeList(int $page = 1, int $perPage = 20, array $filters = []): array;

    /**
     * 공지사항 상세 조회
     */
    public function getNoticeDetail(int $board_notice_seq): array;

    /**
     * 공지사항 생성
     *
     * @param  int  $adminSeq
     */
    public function createNotice(array $data): array;

    /**
     * 공지사항 수정
     */
    public function updateNotice(int $board_notice_seq, array $data): array;

    /**
     * 공지사항 삭제
     */
    public function deleteNotice(int $board_notice_seq): void;
}
