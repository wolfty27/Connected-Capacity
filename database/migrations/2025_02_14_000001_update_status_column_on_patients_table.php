<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        DB::statement("ALTER TABLE patients MODIFY status VARCHAR(50) NULL");
        DB::statement("UPDATE patients SET status = CASE WHEN status = '1' THEN 'Active' WHEN status = '0' THEN 'Inactive' ELSE status END");
    }

    public function down()
    {
        DB::statement("UPDATE patients SET status = CASE WHEN status = 'Active' THEN '1' WHEN status = 'Inactive' THEN '0' ELSE status END");
        DB::statement("ALTER TABLE patients MODIFY status TINYINT(1) NULL");
    }
};
