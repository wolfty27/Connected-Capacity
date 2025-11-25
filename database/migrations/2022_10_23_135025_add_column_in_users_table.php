<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnInUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'role')) {
                $table->string('role')->nullable();
            }
            if (!Schema::hasColumn('users', 'phone_number')) {
                $table->string('phone_number')->nullable();
            }
            if (!Schema::hasColumn('users', 'country')) {
                $table->string('country')->nullable();
            }
            if (!Schema::hasColumn('users', 'image')) {
                $table->string('image')->nullable();
            }
            if (!Schema::hasColumn('users', 'address')) {
                $table->string('address')->nullable();
            }
            if (!Schema::hasColumn('users', 'city')) {
                $table->string('city')->nullable();
            }
            if (!Schema::hasColumn('users', 'state')) {
                $table->string('state')->nullable();
            }
            if (!Schema::hasColumn('users', 'timezone')) {
                $table->string('timezone')->nullable();
            }
            if (!Schema::hasColumn('users', 'zipcode')) {
                $table->string('zipcode')->nullable();
            }
            if (!Schema::hasColumn('users', 'latitude')) {
                $table->string('latitude')->nullable();
            }
            if (!Schema::hasColumn('users', 'longitude')) {
                $table->string('longitude')->nullable();
            }
            if (!Schema::hasColumn('users', 'calendly_status')) {
                $table->integer('calendly_status')->nullable();
            }
            if (!Schema::hasColumn('users', 'calendly_username')) {
                $table->string('calendly_username')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            //
        });
    }
}
