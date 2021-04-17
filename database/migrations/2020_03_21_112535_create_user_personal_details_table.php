<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUserPersonalDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_personal_details', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('user_id')->unsigned();
            $table->string("name")->nullable();
            $table->string("address")->nullable();
            $table->string("city")->nullable();
            $table->string("zip_code")->nullable();
            $table->string("twitter_username")->nullable();
            $table->string("dob")->nullable();
            $table->string("photo_proof")->nullable();
            $table->string("id_proof")->nullable();
            $table->string("id_expiry_date")->comment("null - no expiration date")->nullable();
            $table->tinyInteger("explicit_content")->comment("0-not posting,1-posting")->nullable();
            $table->tinyInteger("verified")->comment("0-not verified,1-verified")->nullable();
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_personal_details');
    }
}
