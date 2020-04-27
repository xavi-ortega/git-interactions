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
            $table->decimal('prc_good_asignees');
            $table->decimal('prc_bad_asignees');
            $table->decimal('prc_good_reviewers');
            $table->decimal('prc_bad_reviewers');
            $table->decimal('prc_unexpected_reviewers');
            $table->decimal('prc_good_reactions');
            $table->decimal('prc_closed_with_commits');
            $table->decimal('prc_commits_by_assignees');
            $table->bigInteger('avg_commits_per_pr');

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
