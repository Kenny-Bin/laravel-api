<?php

namespace App\Models\Admin;

use App\Traits\HasEncryptedFields;

class User extends AdminBaseModel
{
    use HasEncryptedFields;

    protected $table = 'ota.scuser';
    protected $primaryKey = 'scuser_seq';
    public $timestamps = false;

    /**
     * 자동 복호화할 필드 목록
     */
    protected function getEncryptedFields(): array
    {
        return [
            'name_kr',
            'nick',
            'birth',
            'nationality_type',
            'phone_number',
            'country_code',
            'sex',
            'last_name',
            'first_name',
            'hp_number',
        ];
    }
}
