<?php

namespace App\Services\Contracts;

interface AuthServiceInterface
{
    /**
     * 로그인
     *
     * @param  string  $account  ID
     * @param  string  $passwd  비밀번호
     * @return array 관리자 정보
     *
     * @throws \Exception AUTH_FAILED
     */
    public function login(string $account, string $passwd): array;
}
