<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanDetail extends Model
{
    //

    protected $table = "user_plans_details";
    protected $fillable = ["plan_id"];

    public function Plan()
    {
        return $this->belongsTo("App\Plan");
    }
}
