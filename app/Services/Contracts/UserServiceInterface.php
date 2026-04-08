<?php

namespace App\Services\Contracts;

interface UserServiceInterface
{
    /**
     * 회원 목록 조회 (페이징)
     */
    public function getUserList(int $page = 1, int $perPage = 20, array $filters = []): array;

    /**
     * 회원 상세 조회
     */
    public function getUserDetail(int $id): array;

    /**
     * 회원 수정
     */
    public function updateUser(int $id, array $data): array;
}
