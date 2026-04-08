<?php

namespace App\Services\V1;

use App\Helpers\JwtHelper;
use App\Services\BaseService;
use App\Services\Contracts\AuthServiceInterface;
use App\Models\Admin\AdminMember;

class AuthService extends BaseService implements AuthServiceInterface
{
    public function __construct()
    {
        parent::__construct();
    }
    public function login(string $account, string $passwd): array
    {
        $secretKey = env('SECRET_KEY');

        // ORM을 사용하여 비밀번호 복호화 (aes_decrypt 함수 사용)
        $user = AdminMember::selectRaw("
            *,
            aes_decrypt(adm_pwd, ?) as passwd
        ", [$secretKey])
            ->where('adm_id', $account)
            ->first();

        // 사용자가 없거나 비밀번호가 일치하지 않으면 인증 실패
        if (!$user || $user->passwd !== $passwd) {
            throw new \Exception(json_encode([
                'code' => 'AUTH_FAILED',
                'message' => '',
            ]));
        }

        $token = JwtHelper::encode([
            'cln_seq' => $user->adm_seq,
            'email' => $user->account,
        ]);

        // 🎯 인증 성공
        return [
            'user_email' => $user->account,
            'user_name' => $user->adm_name,
            'adm_seq' => $user->adm_seq,
            'token' => $token
        ];
    }
}
