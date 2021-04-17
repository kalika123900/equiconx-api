<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyUser extends Model
{
    protected $table = "company_user";
    protected $fillable = ["company_id", "user_id", "role", "status"];
    
    public function user()
    {
        return $this->belongsTo("App\User");
    }
}
