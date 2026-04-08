<?php

namespace App\Models\Admin;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Admin Member Model
 *
 * admin_member 테이블
 * 관리자 인증 및 토큰 관리를 위한 모델
 */
class AdminMember extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'agladmin.admin_member';
    protected $primaryKey = 'adm_seq';
    public $timestamps = true;

    const CREATED_AT = 'regdt';
    const UPDATED_AT = 'moddt';

    protected $fillable = [
        'adm_id',
        'adm_pwd',
        'adm_name',
        'adm_part_seq',
        'cj_seq',
        'email',
        'works_email',
        'confirm_yn',
        'use_yn',
        'work_yn',
        'rev_msg_yn',
        'memo',
        'outdt',
        'broad_yn',
        'chadmin_yn',
    ];

    /**
     * JSON 직렬화 시 숨길 속성
     * - adm_pwd: bytea 타입이라 JSON 직렬화 불가
     * - tokens: HasApiTokens trait의 관계
     */
    protected $hidden = [
        'adm_pwd',
        'tokens',
    ];

    protected $casts = [
        'adm_seq' => 'integer',
        'adm_part_seq' => 'integer',
        'cj_seq' => 'integer',
        'regdt' => 'datetime',
        'moddt' => 'datetime',
        'outdt' => 'datetime',
    ];
}
