<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReportsProgressTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('reports_progress', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('report_id')->constrained()->onDelete('cascade');
            $table->tinyInteger('type');
            $table->tinyInteger('progress')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('reports_progress');
    }
}
