<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Faq extends Model
{
    //

    protected $table = "faq_master";
    protected $fillable = ["title","meta_title","meta_keyword","slug","image","detail_description","meta_description"];
    public function subject()
    {
        return $this->hasOne("App\Models\Faqsubject","subject_id");
    }
  
}
