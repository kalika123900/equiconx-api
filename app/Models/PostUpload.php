<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class PostUpload extends Model{
    protected $table = "post_uploads";
    public function post()
    {
        return $this->belongsTo('App\Models\Post');
    }
    public function getPublishedAttribute(){

        return Carbon::createFromTimeStamp(strtotime($this->attributes['created_at']) )->diffForHumans();
    }
}
