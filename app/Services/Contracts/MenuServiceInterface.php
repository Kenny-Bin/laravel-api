<?php

namespace App\Services\Contracts;

interface MenuServiceInterface
{
    /**
     * 메뉴 전체 조회 (GNB + SNB)
     *
     * @param  string  $lang  언어 코드 (ko, en, ja, zh 등)
     */
    public function getAllMenus(string $lang = 'ko'): array;

    /**
     * 최종 수정 시간 가져오기
     */
    public function getLastUpdatedTimestamp(): ?string;
}
