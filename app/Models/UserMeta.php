<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserMeta extends Model{
    
    protected $table = "user_meta";

    public $timestamps = false;

    protected $fillable = ["user_id", "key_type", "value"];
  
    public function user()
    {
        return $this->belongsTo("App\User");
    }
}
