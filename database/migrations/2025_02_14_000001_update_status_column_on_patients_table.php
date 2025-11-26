<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->string('status_text', 50)->nullable();
        });

        // PostgreSQL-compatible: cast boolean to text for comparison
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("UPDATE patients SET status_text = CASE WHEN status::text = 'true' OR status::text = '1' THEN 'Active' WHEN status::text = 'false' OR status::text = '0' THEN 'Inactive' ELSE 'pending' END");
        } else {
            DB::statement("UPDATE patients SET status_text = CASE WHEN status = '1' THEN 'Active' WHEN status = '0' THEN 'Inactive' ELSE status END");
        }

        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('patients', function (Blueprint $table) {
            $table->renameColumn('status_text', 'status');
        });
    }

    public function down()
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->tinyInteger('status_boolean')->nullable();
        });

        DB::statement("UPDATE patients SET status_boolean = CASE WHEN status = 'Active' THEN '1' WHEN status = 'Inactive' THEN '0' ELSE status END");

        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('patients', function (Blueprint $table) {
            $table->renameColumn('status_boolean', 'status');
        });
    }
};
