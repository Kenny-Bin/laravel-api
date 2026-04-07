<?php

namespace App\Services\V1;

use App\Services\BaseService;
use App\Services\Contracts\AuthServiceInterface;
use App\Models\Agladmin\AdminMember;

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

        // 채널어드민 접근 권한 확인
        if ($user->chadmin_yn != 'Y') {
            throw new \Exception(json_encode([
                'code' => 'NO_CHADMIN_PERMISSION',
                'message' => '',
            ]));
        }

        // 🎯 인증 성공
        return [
            'adm_seq' => $user->adm_seq,
            'adm_id' => $user->adm_id,
            'adm_name' => $user->adm_name,
            'email' => $user->email,
        ];
    }
}
