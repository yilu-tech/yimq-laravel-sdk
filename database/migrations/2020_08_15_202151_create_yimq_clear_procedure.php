<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateYimqClearProcedure extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $sql = "
DROP PROCEDURE IF EXISTS yimq_clear;
CREATE DEFINER=`root`@`%` PROCEDURE `yimq_clear`(IN type VARCHAR(10), IN ids json)
    SQL SECURITY INVOKER
BEGIN

	DECLARE length INT;
	DECLARE i int DEFAULT 0;
	DECLARE current_id BIGINT;
	set length = JSON_LENGTH(ids);

	WHILE i < length DO
	set current_id = JSON_EXTRACT(ids, CONCAT('$[',i,']'));
	IF type = 'message' THEN
		DELETE from yimq_messages where message_id = current_id;
		DELETE FROM yimq_subtasks WHERE message_id = current_id;
	ELSEIF type = 'process' THEN
		DELETE from yimq_processes WHERE id = current_id;
END IF;
	set i = i+1;
	END WHILE;
	SELECT length as length;
END       
        ";

        $pdo = \DB::connection()->getPdo();
        $pdo->exec($sql);

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $sql = "DROP PROCEDURE IF EXISTS yimq_clear;";
        $pdo = \DB::connection()->getPdo();
        $pdo->exec($sql);
    }
}
