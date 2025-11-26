<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOhMetricsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('oh_metrics')) {
            Schema::create('oh_metrics', function (Blueprint $table) {
                $table->id();
                $table->date('period_start');
                $table->date('period_end');
                $table->string('metric_key');
                $table->decimal('metric_value', 12, 2)->default(0);
                $table->json('breakdown')->nullable();
                $table->timestamp('computed_at');
                $table->uuid('computed_by_job_id')->nullable();
                $table->timestamps();

                $table->unique(['metric_key', 'period_start', 'period_end'], 'oh_metrics_metric_period_unique');
                $table->index('computed_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasTable('oh_metrics')) {
            Schema::dropIfExists('oh_metrics');
        }
    }
}
