<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserFollower extends Model
{
  protected $table = "user_followers";
  protected $fillable = ["user_id", "following"];
}
