<?php

namespace App\Models\Admin;

class ScmenuGnb extends AdminBaseModel
{
    protected $table = 'menu_gnb';
    protected $primaryKey = 'mg_seq';
    protected $casts = [
        'mg_title_language' => 'array'
    ];

    public function snbs()
    {
        return $this->hasMany(ScmenuSnb::class, 'mg_seq', 'mg_seq');
    }

}
