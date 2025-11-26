<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('retirement_homes', function (Blueprint $table) {
            if (!Schema::hasColumn('retirement_homes', 'type')) {
                $table->string('type')->nullable();
            }
        });
    }

    public function down()
    {
        Schema::table('retirement_homes', function (Blueprint $table) {
            if (Schema::hasColumn('retirement_homes', 'type')) {
                $table->dropColumn('type');
            }
        });
    }
};
