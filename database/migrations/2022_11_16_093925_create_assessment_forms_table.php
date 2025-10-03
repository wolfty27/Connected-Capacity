<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAssessmentFormsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('assessment_forms', function (Blueprint $table) {
            $table->id();
            $table->string('patient_id');
            $table->string('secondary_contact_name');
            $table->string('secondary_contact_relationship');
            $table->string('secondary_contact_phone');
            $table->string('secondary_contact_email');
            $table->string('designated_alc');
            $table->string('least_3_days');
            $table->string('pcr_covid_test');
            $table->string('post_acute');
            $table->string('if_yes');
            $table->string('length');
            $table->string('npc');
            $table->string('apc');
            $table->string('bk');
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
        Schema::dropIfExists('assessment_forms');
    }
}
