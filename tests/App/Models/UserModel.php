<?php


namespace Tests\App\Models;


use Illuminate\Database\Eloquent\Model;

class UserModel extends Model
{
    protected $table = 'users';
    protected $fillable = [
        'id',
        'username',
    ];

}