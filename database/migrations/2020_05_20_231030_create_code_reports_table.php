<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCodeReportsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('code_reports', function (Blueprint $table) {
            $table->id();

            $table->foreignId('report_id')->constrained()->onDelete('cascade');
            $table->integer('branches');
            $table->integer('branches_without_activity');
            $table->decimal('prc_new_code');
            $table->decimal('prc_rewrite_others_code');
            $table->decimal('prc_rewrite_own_code');
            $table->json('top_changed_files');

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
        Schema::dropIfExists('code_reports');
    }
}
