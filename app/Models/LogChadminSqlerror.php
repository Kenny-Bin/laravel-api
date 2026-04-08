<?php

namespace App\Models;

use App\Models\Admin\AdminBaseModel;

class LogSqlerror extends AdminBaseModel
{
    protected $table = 'log_chadmin_sqlerror';
    protected $primaryKey = 'seq';

    // create_ts만 있고 last_update_ts가 없음
    public $timestamps = false;

    protected $fillable = [
        'sql_txt',
        'create_ts',
        'script_url',
        'sqlmode',
        'sqlerror',
    ];

    protected $casts = [
        'create_ts' => 'datetime',
    ];

    /**
     * create_ts는 자동으로 설정되도록 (UTC 변환 제외)
     */
    protected $excludeFromUtcConversion = ['create_ts'];
}
