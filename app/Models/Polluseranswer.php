<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Polluseranswer extends Model
{
    //

    protected $table = "post_poll_user_answers";
    protected $fillable = ["post_id","user_id", "options"];
    
}
