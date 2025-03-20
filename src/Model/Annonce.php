<?php

namespace Model;

class Annonce extends \Illuminate\Database\Eloquent\Model {
    protected $table = 'annonce';
    protected $primaryKey = 'id_annonce';
    public $timestamps = false;
    public $links = null;


    public function annonceur()
    {
        return $this->belongsTo('Model\Annonceur', 'id_annonceur');
    }

    public function photo()
    {
        return $this->hasMany('Model\Photo', 'id_photo');
    }

}
?>
