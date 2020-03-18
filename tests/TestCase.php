<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\App\Models\UserModel;
use YiluTech\YiMQ\Models\Message as MessageModel;
use YiluTech\YiMQ\Models\Subtask as SutaskModel;
abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    public $messageTable;
    public $subtaskTable;
    public $userModelTable;
    public function __construct($name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        $this->messageTable = (new MessageModel())->getTable();
        $this->subtaskTable = (new SutaskModel())->getTable();
        $this->userModelTable = (new UserModel())->getTable();

    }

    public function getSubtaskId(){
        $first = SutaskModel::query()->orderByDesc('id')->first();
        $index = $first ?  ++ $first->id : 1;
        return $index;
    }
}
