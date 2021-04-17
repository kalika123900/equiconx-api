<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostPlan extends Model
{
  protected $table = "post_plans";
  protected $fillable = ["post_id", "access_level"];
}
