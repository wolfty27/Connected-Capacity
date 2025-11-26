<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDayDateSlotColumnsToBookings extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('start_time')->nullable();
            $table->timestamp('end_time')->nullable();
            $table->string('event_uri');
            $table->string('invitee_uri');
            //
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Guarded drops so rollback doesn't fail if columns are already absent.
            if (Schema::hasColumn('bookings', 'start_time')) {
                $table->dropColumn('start_time');
            }
            if (Schema::hasColumn('bookings', 'end_time')) {
                $table->dropColumn('end_time');
            }
            if (Schema::hasColumn('bookings', 'event_uri')) {
                $table->dropColumn('event_uri');
            }
            if (Schema::hasColumn('bookings', 'invitee_uri')) {
                $table->dropColumn('invitee_uri');
            }
        });
    }
}
