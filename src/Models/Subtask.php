<?php


namespace YiluTech\YiMQ\Models;


use Illuminate\Database\Eloquent\Model;

class Subtask extends Model
{
    protected $table = 'yimq_subtasks';
    protected $fillable = [
        'id',
        'subtask_id',
        'message_id',
        'type',
        'data',
        'status'
    ];
    protected $casts = [
        'data' => 'json'
    ];
}