<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    //

    protected $table = "user_plans";
    protected $fillable = ["user_id", "plan_name", "plan_id"];
    public function details()
    {
        return $this->hasOne("App\Models\PlanDetail","plan_id");
    }
    public function user()
    {
        return $this->belongsTo("App\User");
    }
}
