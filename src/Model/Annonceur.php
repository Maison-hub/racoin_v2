<?php

namespace Model;

class Annonceur extends \Illuminate\Database\Eloquent\Model
{
    protected $table = 'annonceur';
    protected $primaryKey = 'id_annonceur';
    public $timestamps = false;

    public function annonce(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany('src\model\Annonce', 'id_annonceur');
    }
}
