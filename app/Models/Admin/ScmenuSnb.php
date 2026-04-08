<?php

namespace App\Models\Admin;

class ScmenuSnb extends AdminBaseModel
{
    protected $table = 'menu_snb';
    protected $primaryKey = 'ms_seq';
    protected $casts = [
        'sg_title_language' => 'array',
        'user_gubun' => 'integer'
    ];

    public function gnb()
    {
        return $this->belongsTo(ScmenuGnb::class, 'mg_seq', 'mg_seq');
    }

}
