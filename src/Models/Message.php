<?php


namespace YiluTech\YiMQ\Models;


use Illuminate\Database\Eloquent\Model;
use YiluTech\YiMQ\Constants\MessageServerType;
use YiluTech\YiMQ\Constants\MessageType;

class Message extends Model
{
    protected $table = 'yimq_messages';
    protected $fillable = [
        'id',
        'message_id',
        'parent_process_id',
        'topic',
        'type',
        'data',
        'status',
    ];
    protected $casts = [
        'data' => 'json'
    ];
}