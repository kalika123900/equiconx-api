<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Post extends Model{
    protected $table = "posts";

    public function uploads()
    {
        return $this->hasMany('App\Models\PostUpload');
    }

    public function comments()
    {
        return $this->hasMany('App\Models\Comment');
    }
}
