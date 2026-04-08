<?php

namespace App\Traits;

trait HasPaginationResponse
{
    /**
     * Pagination 응답 생성
     *
     * @param array $items
     * @param int $total
     * @param int $page
     * @param int $perPage
     * @return array
     */
    protected function buildPaginationResponse(array $items, int $total, int $page, int $perPage): array
    {
        $lastPage = $total > 0 ? (int)ceil($total / $perPage) : 1;
        $from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to = min($page * $perPage, $total);

        return [
            'items' => $items,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => $lastPage,
                'from' => $from,
                'to' => $to,
            ]
        ];
    }
}
