<?php

namespace App\Traits;

use Illuminate\Http\Request;

trait HasPagination
{
    protected function getPaginationParams(Request $request): array
    {
        return [
            'page' => max(1, (int) $request->input('page', 1)),
            'per_page' => min(10000, max(1, (int) $request->input('per_page', 20)))
        ];
    }
}
