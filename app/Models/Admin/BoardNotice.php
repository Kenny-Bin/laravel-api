<?php

namespace App\Models\Admin;

class BoardNotice extends AdminBaseModel
{
    protected $table = 'board_notice';
    protected $primaryKey = 'board_notice_seq';

    protected $fillable = [
        'subject_language',
        'content_language',
        'is_top',
        'is_view',
        'writer_name',
        'is_active',
    ];

    /**
     * JSONB 필드를 배열로 자동 변환
     */
    protected $casts = [
        'subject_language' => 'array',
        'content_language' => 'array',
        'is_view' => 'boolean',
        'is_active' => 'boolean',
        'is_top' => 'boolean',
        'view_count' => 'integer',
    ];

    /**
     * 첨부파일과의 관계
     */
    public function files()
    {
        return $this->hasMany(BoardNoticeFile::class, 'board_notice_seq', 'board_notice_seq')
            ->where('is_active', true);
    }
}
