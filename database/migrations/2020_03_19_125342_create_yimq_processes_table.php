<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateYimqProcessesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('yimq_processes', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('producer',20)->index();
            $table->unsignedBigInteger('message_id')->index('message_id');
            $table->unsignedTinyInteger('type')->nullable(false);
            $table->char('processor');
            $table->json('data')->nullable();
            $table->json('try_result')->nullable();
            $table->tinyInteger('status')->nullable(false);
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
        Schema::dropIfExists('yimq_processes');
    }
}
