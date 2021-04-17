<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserVerificationDocument extends Model
{
    //
    protected $table = "user_identity_documents";
    protected $fillable = ["user_id", "path", "document_type", "docuemnt_side", "original_filename"];
}
