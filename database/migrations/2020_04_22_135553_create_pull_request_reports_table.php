<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePullRequestReportsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pull_request_reports', function (Blueprint $table) {
            $table->id();

            $table->foreignId('report_id')->constrained()->onDelete('cascade');
            $table->bigInteger('total');
            $table->bigInteger('open');
            $table->bigInteger('closed');
            $table->bigInteger('closed_without_commits');
            $table->bigInteger('closed_less_than_one_hour');
            $table->decimal('prc_closed_with_commits');
            $table->bigInteger('avg_commits_per_pr');
            $table->string('avg_time_to_close');
            $table->string('avg_time_to_merge');

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
        Schema::dropIfExists('pull_request_reports');
    }
}
