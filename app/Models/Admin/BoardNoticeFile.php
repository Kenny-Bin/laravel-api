<?php

namespace App\Models\Admin;

class BoardNoticeFile extends AdminBaseModel
{
    protected $table = 'board_notice_file';
    protected $primaryKey = 'bord_notice_file_seq';

    protected $fillable = [
        'board_notice_seq',
        'json_file_name',
        'json_file_path',
        'is_active',
    ];

    protected $casts = [
        'json_file_name' => 'array',
        'json_file_path' => 'array',
        'is_active' => 'boolean',
        'create_ts' => 'datetime',
        'last_update_ts' => 'datetime',
    ];

    /**
     * 공지사항과의 관계
     */
    public function notice()
    {
        return $this->belongsTo(BoardNotice::class, 'board_notice_seq', 'board_notice_seq');
    }
}
