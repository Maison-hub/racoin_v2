<?php

namespace Model;

class Photo extends \Illuminate\Database\Eloquent\Model {
    protected $table = 'photo';
    protected $primaryKey = 'id_photo';
    public $timestamps = false;

    public function annonce()
    {
        return $this->belongsTo('src\model\Annonce', 'id_annonce');
    }
}

?>