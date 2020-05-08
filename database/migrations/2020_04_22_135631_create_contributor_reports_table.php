<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateContributorReportsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('contributor_reports', function (Blueprint $table) {
            $table->id();

            $table->foreignId('report_id')->constrained()->onDelete('cascade');
            $table->bigInteger('total');
            $table->bigInteger('avg_files_per_commit');
            $table->bigInteger('avg_lines_per_commit');
            $table->bigInteger('avg_lines_per_file_per_commit');
            $table->bigInteger('avg_pull_request_contributed');
            $table->decimal('avg_prc_good_assignees');
            $table->decimal('avg_prc_bad_assignees');
            $table->decimal('avg_prc_unexpected_contributor');
            $table->decimal('avg_prc_good_reviewers');
            $table->decimal('avg_prc_bad_reviewers');
            $table->decimal('avg_prc_unexpected_reviewers');
            $table->string('avg_time_to_push');
            $table->decimal('prc_new_code');
            $table->decimal('prc_rewrite_others_code');
            $table->decimal('prc_rewrite_own_code');

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
        Schema::dropIfExists('contributor_reports');
    }
}
