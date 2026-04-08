<?php

namespace App\Models\Admin;

class BoardFaq extends AdminBaseModel
{
    protected $table = 'board_faq';
    protected $primaryKey = 'board_faq_seq';

    protected $casts = [
        'ask_language' => 'array',
        'answer_language' => 'array',
        'is_view' => 'integer',
        'is_active' => 'integer',
    ];
}
