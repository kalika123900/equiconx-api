<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Faqsubject extends Model
{
    //

    protected $table = "faq_subject";
    protected $fillable = ["subject"];
    public function faq()
    {
        return $this->hasMany("App\Models\Faq","subject_id","id");
    }
  
}
