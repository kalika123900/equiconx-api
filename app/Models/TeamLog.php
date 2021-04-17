<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeamLog extends Model
{
    //
    protected $table = "team_logs";
    protected $fillable = ["company_id", "user_id", "action_type"];
}
