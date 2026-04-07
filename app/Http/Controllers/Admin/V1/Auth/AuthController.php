<?php

namespace App\Http\Controllers\Admin\V1\Auth;

use App\Http\Controllers\Controller;
use App\Services\Contracts\AuthServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    public function login(Request $request, AuthServiceInterface $svc): JsonResponse
    {
        $validated = $request->validate([
            'account' => 'required|string',
            'passwd' => 'required|string',
        ]);

        return $this->handleServiceCall(function() use ($validated, $svc) {
            return $svc->login(
                $validated['account'],
                $validated['passwd']
            );
        });
    }
}
