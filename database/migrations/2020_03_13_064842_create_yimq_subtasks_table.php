<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateYimqSubtasksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('yimq_subtasks', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('subtask_id')->unique();
            $table->unsignedBigInteger('message_id')->index('message_id');
            $table->unsignedTinyInteger('type')->nullable(false);
            $table->json('data')->nullable();
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
        Schema::dropIfExists('yimq_subtasks');
    }
}
