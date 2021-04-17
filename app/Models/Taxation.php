<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Taxation extends Model
{
    //

    protected $table = "taxation_master";
    protected $fillable = ["customer_id","master_id", "txn_amount", 'country_code'];
    public $timestamps = false;
    
}
