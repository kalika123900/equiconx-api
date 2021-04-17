<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Polloptions extends Model
{
    //

    protected $table = "post_poll_options";
    protected $fillable = ["post_id", "post_options", "post_options_answered"];

    // protected $casts = [
    //     'post_options'  =>  'json',
    // ];
    
}
