<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCalendliesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('calendlies', function (Blueprint $table) {
            $table->id();
            $table->bigInteger("hospital_id");
            $table->longText("code");
            $table->longText("access_token");
            $table->longText("refresh_token");
            $table->string("token_type");
            $table->integer("token_created_at");
            $table->integer("expires_in");
            $table->string("organization");
            $table->string("owner");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('calendlies');
    }
}
