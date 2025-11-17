<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('feature_flags')) {
            Schema::create('feature_flags', function (Blueprint $table) {
                $table->id();
                $table->string('key');
                $table->string('scope')->default('global');
                $table->unsignedBigInteger('target_id')->nullable();
                $table->string('description')->nullable();
                $table->boolean('enabled')->default(false);
                $table->json('payload')->nullable();
                $table->timestamps();
                $table->unique(['key', 'scope', 'target_id']);
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('feature_flags');
    }
};
