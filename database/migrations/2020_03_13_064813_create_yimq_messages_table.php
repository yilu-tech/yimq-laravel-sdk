<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateYimqMessagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('yimq_messages', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('message_id')->unique();
            $table->string('topic',50)->nullable(false);
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
        Schema::dropIfExists('yimq_messages');
    }
}
