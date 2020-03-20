<?php


namespace YiluTech\YiMQ\Models;


use Illuminate\Database\Eloquent\Model;

class ProcessModel extends Model
{
    protected $table = 'yimq_processes';
    protected $fillable = [
        'id',
        'message_id',
        'type',
        'processor',
        'data',
        'try_result',
        'status'
    ];
    protected $casts = [
        'data' => 'json',
        'try_result' => 'json'
    ];
}